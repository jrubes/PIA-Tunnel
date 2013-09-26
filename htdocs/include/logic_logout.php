<?php
if( $_token->pval($_GET['token'], 'process user logout request') !== true ){
    header('Location: /');
}else{
  $_auth->logout();
  header('Location: /');
}
?>