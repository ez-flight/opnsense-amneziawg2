#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2024 AmneziaWG Plugin
 * All rights reserved.
 */

require_once('script/load_phalcon.php');
require_once('util.inc');
require_once('config.inc');
require_once('interfaces.inc');
require_once('system.inc');

/**
 * Execute command with timeout
 */
function exec_with_timeout($command, $timeout = 30)
{
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w")   // stderr
    );
    
    $process = proc_open($command, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return false;
    }
    
    // Set non-blocking mode
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);
    
    $start_time = time();
    $output = '';
    $error = '';
    
    while (true) {
        $status = proc_get_status($process);
        
        if (!$status['running']) {
            break;
        }
        
        if (time() - $start_time > $timeout) {
            proc_terminate($process);
            proc_close($process);
            return false;
        }
        
        // Read output
        $output .= stream_get_contents($pipes[1]);
        $error .= stream_get_contents($pipes[2]);
        
        usleep(100000); // 0.1 second
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    return $return_value === 0;
}

/**
 * Get AmneziaWG general configuration
 */
function get_amneziawg_general_config()
{
    global $config;
    return $config['OPNsense']['amneziawg']['general'] ?? [];
}

/**
 * Get AmneziaWG instances configuration
 */
function get_amneziawg_instances_config()
{
    global $config;
    $raw_instances = $config['OPNsense']['amneziawg']['instance']['instances'] ?? [];

    
    $instances = [];
    
    // Handle the case where instances come as indexed array
    if (isset($raw_instances['instance']) && is_array($raw_instances['instance'])) {
        // Check if it's a numeric array (multiple instances) or associative (single instance)
        if (array_keys($raw_instances['instance']) !== range(0, count($raw_instances['instance']) - 1)) {
            // Single instance as associative array
            $instance_data = $raw_instances['instance'];
            if (isset($instance_data['@attributes']['uuid'])) {
                $uuid = $instance_data['@attributes']['uuid'];
                unset($instance_data['@attributes']);
                $instances[$uuid] = $instance_data;
            }
        } else {
            // Multiple instances as indexed array
            foreach ($raw_instances['instance'] as $index => $instance_data) {
                if (isset($instance_data['@attributes']['uuid'])) {
                    $uuid = $instance_data['@attributes']['uuid'];
                    unset($instance_data['@attributes']);
                    $instances[$uuid] = $instance_data;
                }
            }
        }
    }
    

    return $instances;
}

/**
 * Check if AmneziaWG is enabled
 */
function is_amneziawg_enabled()
{
    $config = get_amneziawg_general_config();
    return isset($config['enabled']) && $config['enabled'] == '1';
}

/**
 * Get AmneziaWG interface name for instance
 */
function get_amneziawg_interface($instance_number)
{
    return "awg{$instance_number}";
}

/**
 * Generate AmneziaWG configuration file for instance
 */
function generate_amneziawg_config($instance_uuid, $instance_config)
{
    $conf_content = "[Interface]\n";
    
    if (!empty($instance_config['private_key'])) {
        $conf_content .= "PrivateKey = {$instance_config['private_key']}\n";
    }
    
    if (!empty($instance_config['listen_port'])) {
        $conf_content .= "ListenPort = {$instance_config['listen_port']}\n";
    }
    
    if (!empty($instance_config['address'])) {
        $conf_content .= "Address = {$instance_config['address']}\n";
    }
    
    if (!empty($instance_config['dns'])) {
        $conf_content .= "DNS = {$instance_config['dns']}\n";
    }
    
    if (!empty($instance_config['table'])) {
        $conf_content .= "Table = {$instance_config['table']}\n";
    }
    
    if (!empty($instance_config['postup'])) {
        $conf_content .= "PostUp = {$instance_config['postup']}\n";
    }
    
    if (!empty($instance_config['preup'])) {
        $conf_content .= "PreUp = {$instance_config['preup']}\n";
    }
    
    if (!empty($instance_config['mtu'])) {
        $conf_content .= "MTU = {$instance_config['mtu']}\n";
    }
    
    // AmneziaWG specific parameters
    if (!empty($instance_config['jc'])) {
        $conf_content .= "Jc = {$instance_config['jc']}\n";
    }
    
    if (!empty($instance_config['jmin'])) {
        $conf_content .= "Jmin = {$instance_config['jmin']}\n";
    }
    
    if (!empty($instance_config['jmax'])) {
        $conf_content .= "Jmax = {$instance_config['jmax']}\n";
    }
    
    if (!empty($instance_config['s1'])) {
        $conf_content .= "S1 = {$instance_config['s1']}\n";
    }
    
    if (!empty($instance_config['s2'])) {
        $conf_content .= "S2 = {$instance_config['s2']}\n";
    }

    if (!empty($instance_config['s3'])) {
        $conf_content .= "S3 = {$instance_config['s3']}\n";
    }

    if (!empty($instance_config['s4'])) {
        $conf_content .= "S4 = {$instance_config['s4']}\n";
    }
    
    if (!empty($instance_config['h1'])) {
        $conf_content .= "H1 = {$instance_config['h1']}\n";
    }
    
    if (!empty($instance_config['h2'])) {
        $conf_content .= "H2 = {$instance_config['h2']}\n";
    }
    
    if (!empty($instance_config['h3'])) {
        $conf_content .= "H3 = {$instance_config['h3']}\n";
    }
    
    if (!empty($instance_config['h4'])) {
        $conf_content .= "H4 = {$instance_config['h4']}\n";
    }

    if (!empty($instance_config['i1']) && trim((string)$instance_config['i1']) !== '') {
        $conf_content .= 'I1 = ' . trim($instance_config['i1']) . "\n";
    }
    
    $conf_content .= "\n[Peer]\n";
    
    if (!empty($instance_config['peer_public_key'])) {
        $conf_content .= "PublicKey = {$instance_config['peer_public_key']}\n";
    }
    
    if (!empty($instance_config['peer_preshared_key'])) {
        $conf_content .= "PresharedKey = {$instance_config['peer_preshared_key']}\n";
    }
    
    if (!empty($instance_config['peer_allowed_ips'])) {
        $conf_content .= "AllowedIPs = {$instance_config['peer_allowed_ips']}\n";
    }
    
    if (!empty($instance_config['peer_endpoint'])) {
        $conf_content .= "Endpoint = {$instance_config['peer_endpoint']}\n";
    }
    
    if (!empty($instance_config['peer_persistent_keepalive'])) {
        $conf_content .= "PersistentKeepalive = {$instance_config['peer_persistent_keepalive']}\n";
    }
    
    if (!empty($instance_config['peer_routes'])) {
        $conf_content .= "Routes = {$instance_config['peer_routes']}\n";
    }
    
    return $conf_content;
}

/**
 * Start AmneziaWG instance
 */
function amneziawg_start_instance($instance_uuid, $instance_config)
{
    $instance_number = $instance_config['instance'] ?? '0';
    $interface = get_amneziawg_interface($instance_number);
    
    // Quick check if interface already exists
    if (does_interface_exist($interface)) {
        return "Interface $interface is already running";
    }
    
    // Load kernel module if needed (with timeout)
    $module_check = shell_exec("timeout 5 kldstat | grep -q if_amn; echo $?");
    if (trim($module_check) !== '0') {
        exec_with_timeout('/sbin/kldload if_amn', 10);
    }
    
    // Generate and write configuration
    $conf_content = generate_amneziawg_config($instance_uuid, $instance_config);
    $conf_file = "/usr/local/etc/amnezia/{$interface}.conf";
    
    // Create directory if it doesn't exist
    if (!is_dir("/usr/local/etc/amnezia")) {
        mkdir("/usr/local/etc/amnezia", 0755, true);
    }
    
    file_put_contents($conf_file, $conf_content);
    
    // Apply configuration using awg-quick with timeout
    $command = "/usr/local/bin/awg-quick up $interface";
    $result = exec_with_timeout($command, 30);
    
    if ($result) {
        return "Interface $interface started successfully";
    } else {
        return "Failed to start interface $interface";
    }
}

/**
 * Stop AmneziaWG instance
 */
function amneziawg_stop_instance($instance_uuid, $instance_config)
{
    $instance_number = $instance_config['instance'] ?? '0';
    $interface = get_amneziawg_interface($instance_number);
    
    if (!does_interface_exist($interface)) {
        return "Interface $interface is not running";
    }
    
    // Stop interface using awg-quick with timeout
    $command = "/usr/local/bin/awg-quick down $interface";
    $result = exec_with_timeout($command, 15);
    
    if ($result) {
        // Remove configuration file after stopping
        $conf_file = "/usr/local/etc/amnezia/{$interface}.conf";
        if (file_exists($conf_file)) {
            unlink($conf_file);
        }
        return "Interface $interface stopped successfully";
    } else {
        return "Failed to stop interface $interface";
    }
}

/**
 * Remove AmneziaWG instance configuration file
 */
function amneziawg_remove_instance_config($instance_uuid, $instance_config)
{
    $instance_number = $instance_config['instance'] ?? '0';
    $interface = get_amneziawg_interface($instance_number);
    $conf_file = "/usr/local/etc/amnezia/{$interface}.conf";
    
    // Stop the instance first if it's running
    if (does_interface_exist($interface)) {
        amneziawg_stop_instance($instance_uuid, $instance_config);
    }
    
    // Remove configuration file
    if (file_exists($conf_file)) {
        unlink($conf_file);
    }
    
    return "Instance $interface configuration removed";
}

/**
 * Restart AmneziaWG instance with new configuration
 */
function amneziawg_restart_instance_with_config($instance_uuid, $instance_config)
{
    $instance_number = $instance_config['instance'] ?? '0';
    $interface = get_amneziawg_interface($instance_number);
    

    
    // Stop the instance first
    $stop_result = amneziawg_stop_instance($instance_uuid, $instance_config);
    
    // Wait a moment
    sleep(1);
    
    // Load kernel module if needed (with timeout)
    $module_check = shell_exec("timeout 5 kldstat | grep -q if_amn; echo $?");
    if (trim($module_check) !== '0') {
        exec_with_timeout('/sbin/kldload if_amn', 10);
    }
    
    // Generate and write new configuration
    $conf_content = generate_amneziawg_config($instance_uuid, $instance_config);
    $conf_file = "/usr/local/etc/amnezia/{$interface}.conf";
    
    // Create directory if it doesn't exist
    if (!is_dir("/usr/local/etc/amnezia")) {
        mkdir("/usr/local/etc/amnezia", 0755, true);
    }
    
    file_put_contents($conf_file, $conf_content);
    
    // Start the interface with new config
    $command = "/usr/local/bin/awg-quick up $interface";
    $result = exec_with_timeout($command, 30);
    
    if ($result) {
        return "Interface $interface restarted successfully with new config";
    } else {
        return "Failed to restart interface $interface";
    }
}



/**
 * Reconfigure AmneziaWG with optional specific instance UUID
 */
function amneziawg_reconfigure($specific_uuid = null)
{
    if (!is_amneziawg_enabled()) {
        return false;
    }
    
    $instances = get_amneziawg_instances_config();
    
    $success = true;
    
    if ($specific_uuid) {
        // Only restart the specific instance
        if (isset($instances[$specific_uuid])) {
            $instance_config = $instances[$specific_uuid];
            $enabled = (string)($instance_config['enabled'] ?? '0');
            $instance_number = $instance_config['instance'] ?? '0';
            $interface = get_amneziawg_interface($instance_number);
            
            if ($enabled === '1') {
                $result = amneziawg_restart_instance_with_config($specific_uuid, $instance_config);
                
                if ($result !== "Interface " . $interface . " restarted successfully with new config") {
                    $success = false;
                }
            } else {
                $result = amneziawg_stop_instance($specific_uuid, $instance_config);
            }
        } else {
            $success = false;
        }
    } else {
        // Restart all instances (original behavior)
        foreach ($instances as $uuid => $instance_config) {
            $enabled = (string)($instance_config['enabled'] ?? '0');
            $instance_number = $instance_config['instance'] ?? '0';
            $interface = get_amneziawg_interface($instance_number);
            

            
            if ($enabled === '1') {
                $result = amneziawg_start_instance($uuid, $instance_config);
                
                if ($result !== "Interface " . $interface . " started successfully" && 
                    $result !== "Interface " . $interface . " is already running") {
                    $success = false;
                }
            } else {
                $result = amneziawg_stop_instance($uuid, $instance_config);
            }
        }
    }
    
    return $success;
}

/**
 * Start all AmneziaWG instances
 */
function amneziawg_start()
{
    if (!is_amneziawg_enabled()) {
        return false;
    }
    
    $instances = get_amneziawg_instances_config();
    
    $success = true;
    
    foreach ($instances as $uuid => $instance_config) {
        $enabled = (string)($instance_config['enabled'] ?? '0');
        $instance_number = $instance_config['instance'] ?? '0';
        $interface = get_amneziawg_interface($instance_number);
        
        if ($enabled === '1') {
            $result = amneziawg_start_instance($uuid, $instance_config);
            
            if ($result !== "Interface " . $interface . " started successfully") {
                $success = false;
            }
        } else {
            $result = amneziawg_stop_instance($uuid, $instance_config);
        }
    }
    
    return $success;
}

/**
 * Stop all AmneziaWG instances
 */
function amneziawg_stop()
{
    $instances = get_amneziawg_instances_config();
    
    foreach ($instances as $uuid => $instance_config) {
        amneziawg_stop_instance($uuid, $instance_config);
    }
    
    return true;
}

/**
 * Get AmneziaWG status
 */
function amneziawg_status()
{
    if (!is_amneziawg_enabled()) {
        return "disabled";
    }
    
    $instances = get_amneziawg_instances_config();
    $running_count = 0;
    $total_count = 0;
    
    foreach ($instances as $uuid => $instance_config) {
        if ((string)$instance_config['enabled'] == '1') {
            $total_count++;
            $instance_number = $instance_config['instance'] ?? '0';
            $interface = get_amneziawg_interface($instance_number);
            
            if (does_interface_exist($interface)) {
                $running_count++;
            }
        }
    }
    
    if ($total_count == 0) {
        return "no_instances";
    } elseif ($running_count == $total_count) {
        return "running";
    } elseif ($running_count > 0) {
        return "partially_running";
    } else {
        return "stopped";
    }
}

/**
 * Generate keypair for AmneziaWG
 */
function amneziawg_gen_keypair()
{
    // Generate private key using awg genkey
    $private_key = shell_exec('/usr/local/bin/awg genkey');
    
    if (!$private_key) {
        return ['status' => 'failed', 'error' => 'Failed to generate private key'];
    }
    
    $private_key = trim($private_key);
    
    // Generate public key from private key using awg pubkey
    $public_key = shell_exec("echo '$private_key' | /usr/local/bin/awg pubkey");
    
    if (!$public_key) {
        return ['status' => 'failed', 'error' => 'Failed to generate public key'];
    }
    
    $public_key = trim($public_key);
    
    if (!empty($private_key) && !empty($public_key)) {
        return ['status' => 'ok', 'privkey' => $private_key, 'pubkey' => $public_key];
    }
    
    return ['status' => 'failed', 'error' => 'Invalid keypair'];
}

// Main execution
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? '';

switch ($action) {
        case 'start':
            $result = amneziawg_start();
            echo $result ? "AmneziaWG started successfully\n" : "Failed to start AmneziaWG\n";
            exit($result ? 0 : 1);
            
        case 'stop':
            $result = amneziawg_stop();
            echo $result ? "AmneziaWG stopped successfully\n" : "Failed to stop AmneziaWG\n";
            exit($result ? 0 : 1);
            
        case 'restart':
            amneziawg_stop();
            sleep(1);
            $result = amneziawg_start();
            echo $result ? "AmneziaWG restarted successfully\n" : "Failed to restart AmneziaWG\n";
            exit($result ? 0 : 1);
            


            
    case 'status':
        echo amneziawg_status();
        break;
            
        case 'reconfigure':
            $specific_uuid = $argv[2] ?? null;
            
        if (is_amneziawg_enabled()) {
                $result = amneziawg_reconfigure($specific_uuid);
                echo $result ? "AmneziaWG reconfigured and started successfully\n" : "Failed to reconfigure AmneziaWG\n";
                exit($result ? 0 : 1);
        } else {
                $result = amneziawg_stop();
                echo $result ? "AmneziaWG reconfigured and stopped successfully\n" : "Failed to reconfigure AmneziaWG\n";
                exit($result ? 0 : 1);
        }
        break;
            
        case 'remove_instance':
            $instance_uuid = $argv[2] ?? null;
            if ($instance_uuid) {
                $instances = get_amneziawg_instances_config();
                if (isset($instances[$instance_uuid])) {
                    $result = amneziawg_remove_instance_config($instance_uuid, $instances[$instance_uuid]);
                    echo $result . "\n";
                    exit(0);
                } else {
                    echo "Instance not found\n";
                    exit(1);
                }
            } else {
                echo "Instance UUID required\n";
                exit(1);
            }
            break;
            
        case 'gen_keypair':
            $keypair = amneziawg_gen_keypair();
            echo json_encode($keypair);
            break;
            
        case 'show':
            // Use the Python script for detailed status
            $command = '/usr/local/opnsense/scripts/AmneziaWG/awg_show.py';
            $output = shell_exec($command);
            if ($output) {
                echo $output;
            } else {
                echo json_encode(['records' => [], 'status' => 'failed', 'error' => 'Failed to execute awg_show.py']);
            }
            break;
            
    default:
            echo "Unknown action: $action\n";
        exit(1);
    }
}
