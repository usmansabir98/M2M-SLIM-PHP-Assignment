<?php

$f_class_path = $app->config('classes.path') . DIRSEP;                      //requies path information and stores it in variable
$f_wrapper_path = $app->config('wrappers.path') . DIRSEP;

require_once $f_class_path . 'ValidateModel.php';                           //variable information is then used for concatenating with the required
require_once $f_class_path . 'SendMessageModel.php';                       //php files which are required_once and loaded
require_once $f_wrapper_path . 'MySQL_Wrapper.php';
require_once $f_wrapper_path . 'Soap_Wrapper.php';

require_once $f_wrapper_path . 'Session_Wrapper.php';
require_once $f_wrapper_path . 'OpenSSL_Wrapper.php';
require_once $f_class_path . 'AppLoggerModel.php';

$app->get('/sendMessage', function() use ($app)
{
    if(!isset($_SESSION['username'])) {                                  //if the SESSION is not set than the user is not logged in
      return  $app->response->redirect($app->urlFor('authentication'));        //therefore the page does not get displayed
    }

    $f_obj_validate = new ValidateModel();                                 //instantiates the required classes
    $f_obj_send_model = new SendMessageModel();
    $f_obj_MySQL = new MySQL_Wrapper();
    $f_obj_MySQL->connect_to_database();
    $f_obj_soap = new Soap_Wrapper();

    //------------------Logger
    $f_obj_openssl_wrapper = new OpenSSLEncr();
    $f_userID = $f_obj_openssl_wrapper->decrypt(Session_Wrapper::get_session('username'));
    $f_userID = Session_Wrapper::get_session('username');

    $f_obj_logger= new AppLoggerModel();
    $f_obj_logger->set_database_handle($f_obj_MySQL);
    $f_obj_logger->do_get_database_connection_result();
    $f_obj_logger->do_logging($f_userID,'Sending message!');
    //----------------------------------------------


    //*********************************************SEND*******************************

    $f_msisdn = $app->request->get('message-id');                       
    $f_msg_bearer = $app->request->get('msg-bearer');  
    $f_delivery_report = $app->request->get('deliver-report');

    // Intercept message values
    $f_s1_val = $app->request->get('s1-val');
    $f_s2_val = $app->request->get('s2-val');
    $f_s3_val = $app->request->get('s3-val');
    $f_s4_val = $app->request->get('s4-val');
    
    $f_fan_val = $app->request->get('fav-val');
    $f_frw_val = $app->request->get('frw-val');
    $f_rev_val = $app->request->get('rev-val');

    $f_h_val = $app->request->get('h-val');
    $f_temp_val = $app->request->get('temp-val');
    $f_key_val = $app->request->get('key-val');

    // Structure a message
    $f_message_body = '&lts1&gt'. $f_s1_val .'&lt/s1&gt';
    $f_message_body .= '&lts2&gt'. $f_s2_val .'&lt/s2&gt';
    $f_message_body .= '&lts3&gt'. $f_s3_val .'&lt/s3&gt';
    $f_message_body .= '&lts4&gt'. $f_s4_val .'&lt/s4&gt';
    $f_message_body .= '&ltfan&gt'. $f_fan_val .'&lt/fan&gt';
    $f_message_body .= '&ltfrw&gt'. $f_frw_val .'&lt/frw&gt';
    $f_message_body .= '&ltrev&gt'. $f_rev_val .'&lt/rev&gt';
    $f_message_body .= '&lth&gt'. $f_h_val .'&lt/h&gt';
    $f_message_body .= '&ltt&gt'. $f_temp_val .'&lt/t&gt';
    $f_message_body .= '&ltk&gt'. $f_key_val .'&lt/k&gt';

    // Fix delivery reports
    if($f_delivery_report) {
        $f_delivery_report = 'true';
    }
    else {
        $f_delivery_report = 'false';
    }

    // Set MSG Bearer
    if(empty($f_msg_bearer)) {
        $f_msg_bearer = ''; //Set the default mode as the message bearer
    }

    
    if(!empty($f_msisdn))                                                 //if message id is empty than it sends all messages
    {
        $f_validated_message =$f_obj_validate->validate_phone($f_msisdn);  //if value is provided than it is validated
    }

    // Server requires an MSIDN beginning with "+"
    $f_msisdn = "+" . $f_msisdn;

    //If validation fails than the number is not valid
    if($f_validated_message===false)
    {
        $f_obj_logger->do_logging($f_userID,': Error in the MSISDN!');
        return $app->redirect('error');
    }


    /*
     * sets the message id and the WSDL file along with initialising the SOAP client handle,
     * once the setup is complete, the messages can be sended
     */

    $f_obj_send_model->set_message_id($f_validated_message);
    $f_obj_soap->set_wsdl('https://m2mconnect.ee.co.uk/orange-soap/services/MessageServiceByCountry?wsdl');
    $f_obj_soap->make_soap_client();

    $f_obj_soap_handle = $f_obj_soap->get_soap_client_handle();

    if($f_obj_soap_handle===null)
    {
        $f_obj_logger->do_logging($f_userID,': Invalid SOAP client handle. ');
        return $app->redirect('error');
    }


    $f_obj_send_model->set_soap_client_handle($f_obj_soap_handle);

    // Actual work!

    $f_obj_send_model->do_send_message($f_delivery_report, $f_message_body, $f_msg_bearer);
    if(!$f_obj_send_model->verifyMessageSent())
    {
        $f_obj_logger->do_logging($f_userID,' Error sending messages. Retry!');
        return $app->redirect('error');
    }
    else
    {
        $f_obj_logger->do_logging($f_userID,'Message sent successfully!');
    }


    return $app->redirect('homepage');

});
