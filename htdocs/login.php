<?PHP
/* @var $_token token() */
date_default_timezone_set('Europe/Berlin');

@header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
@header("Expires: Wed, 09 Mar 1980 08:00:00 GMT");

global $_token;
$pass = array( 'login_user_form' );
$tokens = $_token->pgen( $pass );
?>
<!DOCTYPE HTML>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <link rel="icon" type="image/ico" href="/favicon.ico">
        <link rel="shortcut icon" href="/favicon.ico">
        <title>Login Form</title>
        <style type="text/css">
            body, input {
              font-family: sans-serif;
            }
            .login_form {
                text-align: center;
                font-weight: bold;
            }
            table.center {
                margin-left:auto;
                margin-right:auto;
                border: none;
                padding: 1em;
                border-collapse: collapse;
                width: 345px;
            }
            table.center td{
                text-align: right;
                width: 102px;
            }
        </style>
    </head>
    <body>
        <div class="login_form">
            <p>&nbsp;</p>
          <h1>PIA-Tunnel Management Interface</h1>
            <p>&nbsp;</p>
            <noscript><p>Please enable Javascript to use the advanced UI</p></noscript>
            <form method="post" name="login_form">
                <table class="center">
                    <tr>
                        <td>User:</td>
                        <td><input type="text" id="inp_username" name="username" tabindex="1" size="30" required autofocus></td>
                    </tr>
                    <tr>
                        <td>Password:</td>
                        <td><input type="password" name="password" tabindex="2" size="30"></td>
                    </tr>
                </table>

                <p><input id="btn_submit" tabindex="3" name="Login" value="Login" type="submit"></p>
                <input type="hidden" name="token" value="<?PHP echo $tokens[0]; ?>">
            </form>
        </div>
      <script type="text/javascript">
        document.getElementById('inp_username').focus();
      </script>
    </body>
</html>