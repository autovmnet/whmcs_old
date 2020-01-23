<?php

set_time_limit(0);
#error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once dirname(__FILE__) . '/autovm.class.php';


/**
 * Product options
 */
function autovm_ConfigOptions() {
    return array(
        'serverId' => array(
            'Type' => 'text',
            'Description' => 'Server Id',
        ),
        'datastoreId' => array(
            'Type' => 'text',
            'Description' => 'Datastore Id',
        ),
#        'osId' => array(
#            'Type' => 'text',
#            'Description' => 'Operation System Id',
#        ),
        'planId' => array(
            'Type' => 'text',
            'Description' => 'Plan Id',
        ),
        'vpsRam' => array(
            'Type' => 'text',
            'Description' => 'Ram',
        ),
        'vpsCpuMhz' => array(
            'Type' => 'text',
            'Description' => 'Cpu Mhz',
        ),
        'vpsCpuCore' => array(
            'Type' => 'text',
            'Description' => 'Cpu Core',
        ),
        'vpsHard' => array(
            'Type' => 'text',
            'Description' => 'Hard',
        ),
        'vpsBandwidth' => array(
            'Type' => 'text',
            'Description' => 'Bandwidth',
        ),
    );
}

/*
 * Admin area
 */
function autovm_AdminServicesTabFields($params) {

    $admin = $_SESSION['adminid'];
    $admin = mysql_fetch_assoc(mysql_query("SELECT * FROM tbladmins WHERE id = '$admin'"));

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->serverId = $params['configoption1'];

    $ips = $class->ips();

    $data = '';

    if ($ips) {
        $data = '<select name="autovm[ip]"><option value="">---</option>';

        foreach ($ips as $id => $ip) {
            $data .= '<option value="' . $id . '">' . $ip . '</option>';
        }

        $data .= '</select>';
    }

    if (empty($ips) && $ips !== false) {
        $data = 'There is no free ip';
        $class->log($data);
    }

    $ip = '';

    $class->vpsId = $params['customfields']['vpsid'];

    $info = $class->info();

    if ($info) {
        $ip = $info->ip;
    }

    $url = $params['serverhostname'];

    $result['Ip'] = $ip;
    $result['Select Ip'] = $data;

    if ($ip) {
        $result['View'] = "<a target='_blank' href='{$info->url}'>View</a>";
    }

    if ($admin) {
        $class->email = $admin['email'];
    }

    $adminLogin = $class->adminLogin();

    if ($adminLogin) {
        $adminLogin->url = str_replace('http:', '', $adminLogin->url);
        $result['admin'] = "<iframe src='$adminLogin->url' style='width:100%; height:800px; border:none;'></iframe>";
    }
    
    $serviceId = $params['serviceid'];
    
    if ($ip) {        
        mysql_query("UPDATE tblhosting SET dedicatedip = '{$ip}' WHERE id = {$serviceId}");
    }

    //$login = $class->login();

    //if ($login) {
    //    $result['Login to autovm'] = "<a target='_blank' href='{$login->url}'>Login</a>";
    //}

    return $result;
}

/*
 * Admin area
 */
function autovm_AdminServicesTabFieldsSave($params) {

    if (!empty($_POST['autovm']['ip'])) {
        autovm_CreateAccount($params, $_POST['autovm']['ip']);
    }
}

/**
 * Create account
 */
function autovm_CreateAccount($params, $ipId = null) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->email = $params['clientsdetails']['email'];
    $class->serverId = $params['configoption1'];
    $class->ipId = $ipId;
    $class->datastoreId = $params['configoption2'];
    $class->planId = $params['configoption3'];

    $class->ram = $params['configoption4'];
    $class->cpuMhz = $params['configoption5'];
    $class->cpuCore = $params['configoption6'];
    $class->hard = $params['configoption7'];
    $class->bandwidth = $params['configoption8'];

    $class->hostname = $params['domain'];

    $class->password = autovm_generatePassword($params);

    $class->firstName = $params['clientsdetails']['firstname'];
    $class->lastName = $params['clientsdetails']['lastname'];

    $result = $class->create();

    if (!$result) {
        return false;
    }

    $pId = $params['pid'];
    $serviceId = $params['serviceid'];

    $sql = "SELECT id FROM tblcustomfields
                WHERE relid = '{$pId}'
                    AND fieldname = 'vpsid'";

    $query = mysql_query($sql);
    $qres = mysql_fetch_assoc($query);

    $sql = "UPDATE tblcustomfieldsvalues SET value = '{$result}'
                WHERE relid = '{$serviceId}'
                    AND fieldid = '{$qres['id']}'";

    mysql_query($sql);



    $sql = "SELECT id FROM tblcustomfields
                WHERE relid = '{$pId}'
                    AND fieldname = 'password'";

    $query = mysql_query($sql);
    $qres = mysql_fetch_assoc($query);

    $sql = "UPDATE tblcustomfieldsvalues SET value = '{$class->password}'
                WHERE relid = '{$serviceId}'
                    AND fieldid = '{$qres['id']}'";

    mysql_query($sql);

    if ($class->createdIp) {        
        mysql_query("UPDATE tblhosting SET dedicatedip = '{$class->createdIp}' WHERE id = {$serviceId}");
    }

    return true;
}

/**
 *
 * Change package
 *
 */
function autovm_ChangePackage($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];
    $class->planId = $params['configoption3'];

    $class->ram = $params['configoption4'];
    $class->cpuMhz = $params['configoption5'];
    $class->cpuCore = $params['configoption6'];
    $class->hard = $params['configoption7'];
    $class->bandwidth = $params['configoption8'];

    $result = $class->upgrade();

    if (!$result) {
        return false;
    }

    return true;
}

/**
 * Suspend account
 */
function autovm_SuspendAccount($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->inactive();

    if ($result) {
        return true;
    }

    return false;
}

/**
 * Unsuspend account
 */
function autovm_UnSuspendAccount($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->active();

    if ($result) {
        return true;
    }

    return false;
}

/**
 * Terminate account
 */
function autovm_TerminateAccount($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->delete();

    if ($result) {
        return true;
    }

    return false;
}

/**
 * Renew account
 */
function autovm_Renew($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->resetBandwidth();

    if ($result) {
        return true;
    }

    return false;
}

/**
 * Start vps
 */
function autovm_start($params) {

    $class = new Autovm($params);

    $class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->start();

    if ($result) {
        return "success";
    }

    return "failed";
}

/**
 * Stop vps
 */
function autovm_stop($params) {

    $class = new Autovm($params);

	$class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->stop();

    if ($result) {
        return "success";
    }

    return "failed";
}

/**
 * Restart vps
 */
function autovm_restart($params) {

    $class = new Autovm($params);

	$class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $result = $class->restart();

    if ($result) {
        return "success";
    }

    return "failed";
}

/**
 * Generate password
 */
function autovm_generatePassword($params) {

    return substr(md5($params['clientsdetails']['id']), 0, 6);
}

/**
 * Custom buttons
 */
function autovm_ClientAreaCustomButtonArray() {

    return array(
        "Start Vps" => "start",
        "Stop Vps" => "stop",
        "Restart Vps" => "restart",
    );
}

/**
 * Install vps
 */
function autovm_install($params) {

    $class = new Autovm($params);

	$class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];
    $class->osId = @intval($_POST['os']);
    $class->password = $class->generatePassword();

    $result = $class->install();

    if ($result) {
        //autovm_email($params['clientsdetails']['id']);
    }

    if ($result) {
        return true;
    }

    return false;
}

function autovm_email($id) {

  $command = 'SendEmail';
  $postData = array(
      'messagename' => 'autovm',
      'id' => $id,
      'customtype' => 'autovm',
      'customsubject' => 'Product Welcome Email',
      'custommessage' => '<p>Thank you for choosing us</p><p>Your custom is appreciated</p><p></p>',
  );

  $results = localAPI($command, $postData);
}

/**
 * Client area
 */
function autovm_ClientArea($params) {

    if (isset($_POST['os'])) {
        $success = autovm_install($params);
    }

    $class = new Autovm($params);

	$class->url = $params['serverhostname'];
    $class->key = $params['serverusername'];

    $class->vpsId = $params['customfields']['vpsid'];

    $info = $class->info();

    $os = $class->osList();

    $login = $class->login();

    ob_start();

    require dirname(__FILE__) . '/os.php';

    $text = ob_get_contents();

    ob_end_clean();

    return $text;
}

