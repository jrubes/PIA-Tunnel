<?php
if( $_token->pval($_GET['token']) !== true ){
    header('Location: /');
}else{
  $_auth->logout();
  header('Location: /');
}
?>