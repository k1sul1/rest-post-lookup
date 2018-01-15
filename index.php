<?php
/*
Plugin name: REST Post lookup
Author: Christian Nikkanen
Author URI: http://kisu.li
*/

namespace k1sul1;

require_once "vendor/autoload.php";
require_once "class.db.php";

\k1sul1\db(); // Open the connection

add_action("plugins_loaded", function() {
  register_activation_hook(__FILE__, function() {
    $db = \k1sul1\db();
    $query = $db->prepare("
      DROP TABLE IF EXISTS `wp_rpl_permalinks`;
      CREATE TABLE `wp_rpl_permalinks` (
        `object_id` bigint(20) NOT NULL COMMENT 'JOINable with wp_posts ID',
        `permalink` varchar(2048) NOT NULL COMMENT 'theoretical maximum url length',
        PRIMARY KEY (`object_id`),
        UNIQUE KEY `permalink` (`permalink`(190))
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE={$db->collate};
    ");

    $query->execute();
  });

  add_filter("generate_rewrite_rules", function($wp_rewrite) {
    $db = \k1sul1\db();
    $query = $db->prepare("SELECT ID from wp_posts WHERE post_status NOT IN (?, ?) AND post_type NOT IN (?, ?, ?, ?, ?)");

    $params = ["trash", "auto-draft", "revision", "customize_changeset", "nav_menu_item", "acf-field", "acf-field-group"];
    $query->execute($params);

    $db->exec("CREATE TABLE IF NOT EXISTS wp_rpl_permalinks_temp LIKE wp_rpl_permalinks");
    $db->exec("TRUNCATE TABLE wp_rpl_permalinks_temp");

    // In order to make this actually fast, generate a string query with VALUES(?,?) VALUES(?,?)...
    // Should be about 8 times faster.

    $insert = $db->prepare("INSERT INTO wp_rpl_permalinks_temp (object_id, permalink) VALUES(?, ?)");
    while ($row = $query->fetch()) {
      $object_id = (int) $row["ID"];
      $permalink = get_permalink($object_id);

      try {
        $insert->execute([$object_id, $permalink]);
      } catch (PDOException $e) {
        error_log(print_r([$object_id, $permalink], true));
        error_log(print_r($e, true));
      }
    }

    $db->exec("RENAME TABLE wp_rpl_permalinks TO wp_rpl_permalinks_old, wp_rpl_permalinks_temp TO wp_rpl_permalinks");
    $db->exec("DROP TABLE wp_rpl_permalinks_old");

    // Leave the rules unharmed. We're doing nothing with them.
    return $wp_rewrite->rules;
  });

  add_action("save_post", function($post_id) {
    if (!wp_is_post_revision($post_id)) {
      $db = \k1sul1\db();
      $permalink = get_permalink($post_id);
      $insert = $db->prepare("INSERT INTO wp_rpl_permalinks (object_id, permalink) VALUES(?, ?) ON DUPLICATE KEY UPDATE permalink = ?");

      $insert->execute([$post_id, $permalink, $permalink]);
    }
  });

  // add_action("admin_bar_menu", function($bar) {
    // $bar->add_node([
      // "id" => "rpl_regen_permalinks",
      // "title" => "Regenerate permalinks",
      // "href" => "/"
    // ]);
  // });

  add_action("rest_api_init", function() {

    register_rest_route("rpl/v1", "lookup", [
      "methods" => "GET",
      "callback" => function() {
        $url = $_GET["url"];
        $legacy = false;

        $getResponse = function($id) {
          $post = get_post($id);
          $type = $post->post_type;
          $ptypeObject = get_post_type_object($type);
          $endpoint = !empty($ptypeObject->rest_base) ? $ptypeObject->rest_base : $type;

          setup_postdata($post); // The response may be faster with this.

          $req = new \WP_REST_Request("GET", "/wp/v2/{$endpoint}/$id");

          // None of these work!
          // can't get an answer: https://twitter.com/k1sul1/status/945950740106838016
          // set_query_params should work according to this https://developer.wordpress.org/reference/classes/wp_rest_request/


          // $req->set_param("_embed", 1);
          // $req["_embed"] = true;
          // $req->set_query_params([
            // "_embed" => true,
            // "embed" => true,
            // "embedded" => true,
            // "_embedded" => true,
          // ]);

          return rest_do_request($req);
        };

        if (!$legacy) {
          // should use wpdb for this so a separate connection isn't needed
          $query = \k1sul1\db()->prepare("SELECT object_id FROM wp_rpl_permalinks WHERE permalink = ? LIMIT 1");
          $query->execute([$url]);
          $id = $query->fetchColumn(0);

          error_log(print_r($_REQUEST, true));
          error_log(print_r($_COOKIE, true));
          // error_log(wp_get_current_user());;

          error_log("check for authentication before giving a post that is private!");

          if ($id !== false) {
            return $getResponse($id);
          } else {
            $response =  new \WP_REST_Response([
              "error" => "No post found.",
            ]);

            return $response;
          }
        } else {
          $id = url_to_postid($url);

          if ($id === 0) {
            $response =  new \WP_REST_Response([
              "error" => "No post found.",
            ]);

            return $response;
          }


          return $getResponse($id);
        }
      },
    ]);
  });
});

