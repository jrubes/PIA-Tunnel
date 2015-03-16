/*
 * this object will create http requests and support aborting a pending request for the same "request_group"
 * "request_groups" are things like "load_ui" so that one "load_ui" call will not overwrite anohter
 */


function RequestHandler(){
  "use strict";
  this.http_request = new Object(); //this object will hold any http request objects

  /* private method to get XMLHttpRequest object */
  this.get_http_obj = get_http_obj;
  function get_http_obj()
  {
    var req;

    try{
      /* Opera 8.0+, Firefox, Safari */
      req = new XMLHttpRequest();
    }catch(e){
      /* Internet Explorer Browsers */
      try{
        req = new ActiveXObject("Msxml2.XMLHTTP");
      }catch(e){
        try{
          req = new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e){
          /* Something went wrong */
          alert('Unable to create HTTP request object. Your browser might be out of date.');
          return false;
        }
      }
    }
    return req;
  }

  /**
   * method to post data
   * see top of this class for definition of request_group
   */
  this.post = post;
  function post( request_group, target_url, data, callback ){
    var _this = this;

    //check if there is an open request for the current class
    if( _this.http_request.request_group ){
      console.debug('aborting pending request for group: ' + request_group);
      _this.http_request.request_group.abort();
    }
    console.debug('creating new request for group: ' + request_group);
    _this.http_request.request_group = _request.get_http_obj();

    _this.http_request.request_group.onreadystatechange = function(){
      if( _this.http_request.request_group.readyState == 4
            && _this.http_request.request_group.status == 200 ){
        //execute action
        if( typeof callback === 'function' ){
          callback( _this.http_request.request_group.responseText );
          delete _this.http_request.request_group;
        }
      }else if( _this.http_request.request_group.readyState == 4
                  && _this.http_request.request_group.status != 200 ){
        // server down
        //alert('error communicating with the server. please check your network connection ');
      }
    };


    _this.http_request.request_group.open("POST", target_url+'?'+Math.random() , true);
    _this.http_request.request_group.setRequestHeader("Content-type", "application/x-www-form-urlencoded;charset=UTF-8");
    _this.http_request.request_group.send(data);


  }
}

