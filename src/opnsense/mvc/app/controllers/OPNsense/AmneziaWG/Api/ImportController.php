<?php

/*
 * Copyright (C) 2024 AmneziaWG Plugin
 * All rights reserved.
 */

namespace OPNsense\AmneziaWG\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class ImportController extends ApiControllerBase
{
    public function parseAction()
    {
        if ($this->request->isPost()) {
            $config = $this->request->getPost('config');
            if (empty($config)) {
                return ['status' => 'failed', 'message' => 'No configuration provided'];
            }
            
            $parsed = $this->parseAmneziaWGConfig($config);
            if ($parsed === false) {
                return ['status' => 'failed', 'message' => 'Invalid configuration format'];
            }
            
            return ['status' => 'ok', 'data' => $parsed];
        }
        return ['status' => 'failed', 'message' => 'Invalid request method'];
    }
    
    public function createAction()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            
            // Validate required fields
            if (empty($data['name']) || empty($data['private_key']) || empty($data['peer_public_key']) || empty($data['peer_endpoint'])) {
                return ['status' => 'failed', 'message' => 'Required fields are missing'];
            }
            
            // Create instance using the Instance API
            $backend = new Backend();
            $response = $backend->configdRun('amneziawg instance add', json_encode($data));
            
            if (strpos($response, 'OK') !== false) {
                return ['status' => 'ok', 'message' => 'Instance created successfully'];
            } else {
                return ['status' => 'failed', 'message' => 'Failed to create instance: ' . $response];
            }
        }
        return ['status' => 'failed', 'message' => 'Invalid request method'];
    }
    
    private function parseAmneziaWGConfig($config_text)
    {
        $lines = explode("\n", $config_text);
        $interface_section = false;
        $peer_section = false;
        $parsed = [
            'name' => '', 'description' => '', 'private_key' => '', 'listen_port' => '', 'address' => '',
            'dns' => '', 'table' => '', 'postup' => '', 'preup' => '', 'mtu' => '',
            'jc' => '', 'jmin' => '', 'jmax' => '', 's1' => '', 's2' => '', 's3' => '', 's4' => '',
            'h1' => '', 'h2' => '', 'h3' => '', 'h4' => '', 'i1' => '',
            'peer_public_key' => '', 'peer_preshared_key' => '', 'peer_allowed_ips' => '',
            'peer_endpoint' => '', 'peer_persistent_keepalive' => '', 'peer_routes' => ''
        ];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if ($line === '[Interface]') {
                $interface_section = true;
                $peer_section = false;
                continue;
            }
            if ($line === '[Peer]') {
                $interface_section = false;
                $peer_section = true;
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if ($interface_section) {
                switch ($key) {
                    case 'PrivateKey': $parsed['private_key'] = $value; break;
                    case 'ListenPort': $parsed['listen_port'] = $value; break;
                    case 'Address': $parsed['address'] = $value; break;
                    case 'DNS': $parsed['dns'] = $value; break;
                    case 'Table': $parsed['table'] = $value; break;
                    case 'PostUp': $parsed['postup'] = $value; break;
                    case 'PreUp': $parsed['preup'] = $value; break;
                    case 'MTU': $parsed['mtu'] = $value; break;
                    case 'Jc': $parsed['jc'] = $value; break;
                    case 'Jmin': $parsed['jmin'] = $value; break;
                    case 'Jmax': $parsed['jmax'] = $value; break;
                    case 'S1': $parsed['s1'] = $value; break;
                    case 'S2': $parsed['s2'] = $value; break;
                    case 'S3': $parsed['s3'] = $value; break;
                    case 'S4': $parsed['s4'] = $value; break;
                    case 'H1': $parsed['h1'] = $value; break;
                    case 'H2': $parsed['h2'] = $value; break;
                    case 'H3': $parsed['h3'] = $value; break;
                    case 'H4': $parsed['h4'] = $value; break;
                    case 'I1': $parsed['i1'] = $value; break;
                    /* AWG 1.x only; ignored for AWG 2.0 */
                    case 'UserLand': break;
                }
            } elseif ($peer_section) {
                switch ($key) {
                    case 'PublicKey': $parsed['peer_public_key'] = $value; break;
                    case 'PresharedKey': $parsed['peer_preshared_key'] = $value; break;
                    case 'AllowedIPs': $parsed['peer_allowed_ips'] = $value; break;
                    case 'Endpoint': $parsed['peer_endpoint'] = $value; break;
                    case 'PersistentKeepalive': $parsed['peer_persistent_keepalive'] = $value; break;
                    case 'Routes': $parsed['peer_routes'] = $value; break;
                }
            }
        }
        if (empty($parsed['name'])) {
            // Generate name and instance number based on next available number
            $next_number = $this->getNextInstanceNumber();
            $parsed['name'] = 'awg' . $next_number;
            $parsed['instance'] = $next_number;
        }
        if (empty($parsed['private_key']) || empty($parsed['peer_public_key'])) {
            return false;
        }
        return $parsed;
    }
    
    private function getNextInstanceNumber()
    {
        // Get existing instances using the Instance model
        $instanceModel = new \OPNsense\AmneziaWG\Instance();
        $instances = $instanceModel->getNodes();
        
        $used_numbers = [];
        if ($instances && isset($instances['instances']['instance'])) {
            foreach ($instances['instances']['instance'] as $instance) {
                if (isset($instance['instance'])) {
                    $used_numbers[] = (int)$instance['instance'];
                }
            }
        }
        
        // Find the next available number
        $next_number = 0;
        while (in_array($next_number, $used_numbers)) {
            $next_number++;
        }
        
        return $next_number;
    }
} 