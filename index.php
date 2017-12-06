<?php
/*
Plugin name: Expose more pagedata in REST
Author: Christian Nikkanen
Author URI: http://kisu.li
*/

add_action("plugins_loaded", function() {
  $homepage = get_option("page_on_front");
  $blogpage = get_option("page_for_posts");

  $isHomepage = function($page) use ($homepage) {
    return (int) $homepage === $page["id"];
  };
  add_action("rest_api_init", function() use ($isHomepage) {
    register_rest_field("page", "isHomepage", [
      "get_callback" => $isHomepage,
    ]);
  });

  $isBlogpage = function($page) use ($blogpage) {
    return (int) $blogpage === $page["id"];
  };
  add_action("rest_api_init", function() use ($isBlogpage) {
    register_rest_field("page", "isBlogpage", [
      "get_callback" => $isBlogpage,
    ]);
  });
});
