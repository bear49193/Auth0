<script id="auth0" src="<?php echo $cdn ?>"></script>
<script type="text/javascript">
(function(){

  var uuids = <?php echo json_encode( $ids ); ?>;
  document.addEventListener("DOMContentLoaded", function() {
    var lock = new Auth0Lock('<?php echo $client_id; ?>', '<?php echo $domain; ?>');
    lock.$auth0.getSSOData(function(err, data) {
      if (!err && ( !data.sso || uuids.indexOf(data.lastUsedUserID) === -1 ) ) {

        window.location = '<?php echo html_entity_decode( $logout_url ); ?>';

      }
    });
  });

})();
</script>
