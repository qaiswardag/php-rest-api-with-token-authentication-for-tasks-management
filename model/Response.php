<?php

class Response
{
    private $_success;
    private $_httpStatusCode;
    private $_messages = array();
    private $_data;
    // we don't want to cash every response
    // initially response is set to false
    private $_toCache = false;
    private $_responseData = array();

    //
    // setters
    // functions to set private variables
    public function setSuccess($success)
    {
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode)
    {
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message)
    {
        $this->_messages[] = $message;
    }

    public function setData($data)
    {
        $this->_data = $data;
    }

    public function toCache($toCache)
    {
        $this->_toCache = $toCache;
    }

    // send response to client
    // build response data
    // build Response Data - witch will return $_responseData as a response in JSON format back to client
    public function send()
    {
        // tell the client when this response comes back, what type of data it is.
        // header function is used for that
        // tell client what type of character it is - as good practice
        header('Content-Type: application/json;charset=utf-8');

        // tell whether the client can cache the response or not
        if ($this->_toCache == true) {
            // saves a lot of load on the server
            header('Cache-control: max-age=60');
        } else {
            // sometimes clients have their own cache method. we need to explicitly say that this response cannot be cached
            // no-cache and no-store do not store any of the response at all on the client. it always has to come back to the server to get a response
            header('Cache-control: no-cache, no-store');
        }


        // check if the response that we are creating is valid before we send it back
        // if not we will generate a standard five hundred state code, which is a server error
        // basically an if statement for handling errors
        if (($this->_success !== false && $this->_success !== true) || !is_numeric(($this->_httpStatusCode))) {
            // php function for response code
            http_response_code(500);

            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;
            $this->addMessage("Response creation error");
            $this->_responseData['messages'] = $this->_messages;
        }
        //
        // if successful
        else {
            // php function for response code
            http_response_code($this->_httpStatusCode);

            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] = $this->_success;
            $this->_responseData['messages'] = $this->_messages;
            $this->_responseData['data'] = $this->_data;
        }

        // the response data set up
        // we now need to return the data to the browser or to the client
        // in order to do that, we echo it out using php json_encode() function
        // the json_encode() function will automatically convert data to json
        echo json_encode($this->_responseData);

        // test:
        // test the response class
        // create a test file that will call this response


    }

}
