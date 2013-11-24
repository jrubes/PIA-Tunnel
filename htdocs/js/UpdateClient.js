/*
 * javascript to handle dynamic events for the PIA-Web UI
 */

var _request = new RequestHandler();
"use strict";

/* object handeling "Overview" status updates every few seconds */
function UpdateClient( ){

  /* remove repo log button, display update log textarea, run update */
  this.get_git_log = get_git_log;
  function get_git_log( log_count ){

    var url = '/get_gitlog.php';
    var pdata = ''; //post data as string
    var callback;

    pdata = 'count='+encodeURIComponent(log_count);

    callback = function(ret){
      var ele = document.getElementById('uc_feedback_txt');
      var log = document.createTextNode(ret);
      document.getElementById('uc_feedback').removeAttribute('class');

      ele.innerHTML = '';
      ele.appendChild(log);

    };

    _request.post( 'pia_update', url, pdata, callback);
  }

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
    document.getElementById('pia-update').disabled = true;

    var ele = document.getElementById('toggle_git_log');
    if( ele ){ ele.parentNode.removeChild(ele); }
    var ele = document.getElementById('toggle_git_updatelog');
    if( ele ){ ele.parentNode.removeChild(ele); }

    //textarea may already contain repo log data
    var ele = document.getElementById('uc_feedback_txt');
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

    callback = function(ret){
      var ele = document.getElementById('uc_feedback_txt');
      var text = ele.innerHTML + "\n" + ret;

      var log = document.createTextNode(text);

      ele.innerHTML = '';
      ele.appendChild(log);

      document.getElementById('update_refresh').innerHTML = '';
      document.getElementById('pia-update').disabled = false;
    };

    _request.post( 'pia_update', url, pdata, callback);
  }



}