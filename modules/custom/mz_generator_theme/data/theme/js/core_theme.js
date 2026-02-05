function include(filePath) {
    var httpRequest = new XMLHttpRequest();

    if (!httpRequest) {
      console.log('Cannot create an XMLHTTP instance');
      return false;
    }

    httpRequest.onreadystatechange = function() {
      if (httpRequest.readyState === XMLHttpRequest.DONE) {
        if (httpRequest.status === 200) {
            //console.log(httpRequest.responseText);
        } else {
            console.log('There was a problem with the request.');
        }
      }
    };

    httpRequest.open('GET', filePath);
    httpRequest.send();
}