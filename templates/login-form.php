<?php
function renderAuth0Form($canShowLegacyLogin = true, $specialSettings = array())
{
    if(is_user_logged_in())
        return;

    if (!$canShowLegacyLogin || !isset($_GET['wle'])) {
        require_once ('auth0-login-form.php');
    }else{
        add_action('login_footer', array('WP_Auth0', 'render_back_to_auth0'));
    }
}

?>