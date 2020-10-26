<?php
function mo_saml_show_addons_page(){
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

    ?><?php

    $addons_displayed = array();

    ?>
    <div id="miniorange-addons" style="position:relative;z-index: 1">

    <h3 id="recommended_section" style="padding-left: 20px;margin-bottom: -10px; display:none">Recommended Add-ons for you:</h3>


    <?php

    foreach(mo_saml_options_addons::$RECOMMENDED_ADDONS_PATH as $key => $value){
        if (is_plugin_active($value)) {
            $addon = $key;
            $addons_displayed[$addon] = $addon;
            echo get_addon_tile($addon, mo_saml_options_addons::$ADDON_TITLE[$addon],mo_saml_options_addons::$ADDON_DESC[$addon], mo_saml_options_addons::$ADDON_URL[$addon], true);

        }
    }

    ?>

    <?php

    if(!empty($addons_displayed)){
        ?>

        <script>
            document.getElementById("recommended_section").style.removeProperty("display");
        </script>
        <hr class="recommended_section" style="clear:both;color: blue;visibility: hidden;">
        <br/>
        <?php
    }

    ?>
    <h3 style="padding-left: 20px;margin-bottom: -10px; ">Check out all our add-ons:</h3>
    <?php


    foreach (mo_saml_options_addons::$ADDON_DESC as $key => $value) {
        if(!in_array($key, $addons_displayed))
            echo get_addon_tile($key, mo_saml_options_addons::$ADDON_TITLE[$key],$value, mo_saml_options_addons::$ADDON_URL[$key], false);
    }
    ?>
    </div>
        <?php
}



function get_addon_tile($addon_name, $addon_title, $addon_desc, $addon_url, $active){
    $icon_url = plugins_url("images/addons_logos/" . $addon_name .".png", __FILE__);
    $html='<div class="outermost-div">
  <div class="row-view">
    <div class="grid_view column_container">
      <div class="column_inner">';
    if($active)
        $html.= '<div class="row benefits-outer-block-active">';
    else
        $html.= '<div class="row benefits-outer-block">';

    $html.= '<img src="'.$icon_url.'" width="40px" height="40px">
          <h5 class="addon_h5"style="margin-top:1em;">';
    if(!empty($addon_url))
        $html .= '<a class="addon_a" href="'.$addon_url.'"target="_blank">';

    $html .= '<u>'.$addon_title.'</u>';

    if(!empty($addon_url))
        $html.= '</a>';

    $html.= '</h5>
      <p>'.$addon_desc.'</p>';
    if(!empty($addon_url))
        $html.= '<a href="'.$addon_url.'" target="_blank"><button class="button_addons_more" type="button">Learn More</button></a>';


    $html.= '</div>
      </div>
    </div>  
  </div>
</div>';



    return $html;
}

?>