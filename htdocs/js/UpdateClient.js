/*
 * javascript to handle dynamic events for the PIA-Web UI
 */

var _request = new RequestHandler();
"use strict";

/* object handeling "Overview" status updates every few seconds */
function UpdateClient( ){

  /* reconfigure the UI for js operation */
  this.enhance_ui = enhance_ui;
  function enhance_ui(){
    var ele = document.getElementById('pia-update');
    if( ele ){
      ele.type = 'button';
      ele.setAttribute('onclick', "var _update = new UpdateClient(); _update.start_update(); return false;");
    }
  }

  /* remove repo log button, display update log textarea, run update */
  this.start_update = start_update;
  function start_update(){
    var ele = document.getElementById('toggle_git_log');
    if( ele ){ ele.parentNode.removeChild(ele); }

    //textarea may already contain repo log data
    var ele = document.getElementById('git_log_txt');
    if( ele ){
      var log = document.createTextNode("starting online update ....\n");

      ele.innerHTML = '';
      ele.appendChild(log);
    }

    //unhide textarea
    var ele = document.getElementById('git_log');
    if( ele ){
      ele.removeAttribute('class');
    }

    var url = '/run_update.php';
    var pdata = ''; //post data as string
    var callback;

    _request.post( 'pia_update', url, pdata, callback);
  }



}