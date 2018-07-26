#!/usr/bin/php
<?php

$config = [
	"OP5" => [
		"LIST_QUERIES" => [
			[
				"USERPWD" => 'api$Default:api',
				"HOST" => 'https://YOUR.OP5.URL/api/filter/query',
				"FILTERS" => [
					"op5hosts" => [
						"FILTER" => '?format=json&query=[hosts]groups>="YOUR_HOST_GROUP"',
						"COLUMNS" => "&columns=name,address",
						"HOST_VARS" => [
							"ansible_port" => [
								22, 22022
							],
							"ansible_host" => 'address'
						],
						"LIMIT" => 10,
						"OFFSET" => null,
					]
				],
				"GROUP_VARS" => []
			]
		],
		"HOST_QUERIES" => [
			[
				"USERPWD" => 'api$Default:api',
				"HOST" => 'https://YOUR.OP5.URL/api/filter/query',
				"QUERY" => '?format=json&query=[hosts]name= ',
				"COLUMNS" => "&columns=name,address",
				"VARS" => [
					"ansible_port" => [
						22, 22022
					],
					"ansible_host" => 'address'
				],
			]
		]
	],
];

const OP5_API_ADDRESS = 'https://YOUR.OP5.URL/api/filter/query';
const OP5_API_GET_HOSTS_QUERY = '?format=json&query=[hosts]groups>="YOUR_HOST_GROUP"';
const OP5_API_GET_HOST_QUERY = '?format=json&query=[hosts]name = ';
const OP5_API_QUERY_COLUMNS = "&columns=name,address";
const OP5_API_USERPWD = 'api$Default:api';
const OP5_HOST_LIMIT = 10;
const OP5_HOST_OFFSET = null;

const ANSIBLE_PORTS = array(
	22, 22022
);

/**
 * Get a host list from OP5 as JSON
 * 
 * @return array
 */
function get_host_list_from_op5($static_limit) {
	global $config;

	$allHosts = array();
	$i = 0;
	foreach($config["OP5"]["LIST_QUERIES"] as $listQueries) {
		$allHosts[$i] = array();
		foreach($listQueries["FILTERS"] as $filterName => $filter) {
			//var_dump($filter);
			$call =  $filter["FILTER"] . $filter["COLUMNS"];
			$call .= $static_limit ? "&limit=" . $static_limit : "";
			$call .= !$static_limit && $filter["LIMIT"] ? "&limit=" . $filter["LIMIT"] : "";
			$call .= $filter["OFFSET"] ? "&offset=" . $filter["OFFSET"] : "";
			$call = str_replace(" ", "%20", $call);
			//echo $listQueries["HOST"] . $call . "\n";
			$a_handle = curl_init($listQueries["HOST"] . $call);
			curl_setopt($a_handle, CURLOPT_USERPWD, $listQueries["USERPWD"]);
			curl_setopt($a_handle, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($a_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($a_handle, CURLOPT_SSL_VERIFYPEER, false);

			$result = curl_exec($a_handle);
			$data = json_decode($result, true);

			$allHosts[$i][$filterName] = $data; 
		}
		$i++;
	}

	return $allHosts;
}

/**
 * Get a host from OP5 as JSON
 * 
 * @param string $hostName
 * 
 * @return array
 */
function get_host_from_op5($hostName) {
	global $config;

	$host = array();

	foreach($config["OP5"]["HOST_QUERIES"] as $key => $hostQueries) {
		$call =  $hostQueries["QUERY"] . '"' . $hostName . '"' . $hostQueries["COLUMNS"];
		$call = str_replace(" ", "%20", $call);
		$a_handle = curl_init($hostQueries["HOST"] . $call);
		curl_setopt($a_handle, CURLOPT_USERPWD, $hostQueries["USERPWD"]);
		curl_setopt($a_handle, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($a_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($a_handle, CURLOPT_SSL_VERIFYPEER, false);
	
		$result = curl_exec($a_handle);
		$data = json_decode($result, true);

		if($data) {
			$host[$key] = $data;
			break; 
		}
	}

	return $host;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $hosts
 * 
 * @return array
 */
function filter_active_hosts($hosts) {
	global $config;
	
	foreach($hosts as $key => $host) {
		foreach($host as $filterName => $filter) {
			$filter_hosts = array();
			for($i = 0; $i < count($filter); $i++) {

				$port = get_ansible_port($filter[$i], $config["OP5"]["LIST_QUERIES"][$key]["FILTERS"][$filterName]["HOST_VARS"]["ansible_port"]);

				if($port) {
					$filter[$i]['ansible_port'] = $port;
					array_push($filter_hosts, $filter[$i]);
				}
			}
			$hosts[$key][$filterName] = $filter_hosts;
		}
	}

	return $hosts;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $host
 * 
 * @return array
 */
function is_host_active($hosts) {
	global $config;

	foreach($hosts as $key => $host) {
		$port = get_ansible_port($host[0], $config["OP5"]["HOST_QUERIES"][$key]["VARS"]["ansible_port"]);

		if($port) {
			$hosts[$key][0]['ansible_port'] = $port;
		} else {
			$hosts[$key][0] = array();
		}
	}
	
	return $hosts;
}

/**
 * Get active ansible port
 * 
 * @param array $host
 * 
 * @return int
 */
function get_ansible_port($host, $ports) {
	foreach($ports as $port) {
		if ($fp = @fsockopen($host['address'], $port, $errno, $errstr, 1)) { 
			fclose($fp);
			return $port;					
		}
	}

	return null;
}

/**
 * Parse OP5 host list to a ansible json string
 * 
 * @param array $hosts
 * 
 * @return string
 */
function parse_host_list_to_ansible_json($hosts, $group = "hosts") {
	$main = array("_meta" => array("hostvars" => array()));
	foreach($hosts as $index => $group) {
		foreach($group as $filterName => $filter) {
			$main[$filterName]["hosts"] = array();
			foreach($filter as $host) {
				array_push($main[$filterName]["hosts"], $host['name']);
				$main["_meta"]["hostvars"][$host['name']] = array(
					'ansible_port' => $host['ansible_port'],
					'ansible_host' => $host['address'],
				);
			}
		}
	}

	return json_encode($main);
}

/**
 * Parse OP5 host to a ansible vars json string
 * 
 * @param array $host
 * 
 * @return string
 */
function parse_host_vars_to_ansible_json($host) {
	$main = array(
		'ansible_port' => $host['ansible_port'],
		'ansible_host' => $host['address'],
	);
	return json_encode($main);
}

/**
 * Create a static inventory list file
 * 
 * @param array $data
 * @param string $filename
 * @param string $group
 * @param boolean $append
 */
function create_static_inventory_file($data, $filename, $group = "hosts", $append = false) {
	echo "total: " . count($data) . "\n";
	$myfile = fopen($filename, $append ? "a" : "w") or die("Unable to open file!");
	fwrite($myfile, $group . "\n");
	foreach ($data as $host) {

		if ($fp = @fsockopen($host['address'], 22,$errno, $errstr, 1)) { $port=22; fclose($fp);}
		elseif ($fp = @fsockopen($host['address'], 22022, $errno, $errstr, 1)) { $port=22022; fclose($fp); }
		else $port=null;;
		if ($port) {
			echo $host['name'] . ' ansible_port=' . $port . ' ansible_host=' . $host['address'];
			echo "\n";
			fwrite($myfile,  $host['name'] . ' ansible_port=' . $port . ' ansible_host=' . $host['address'] . "\n");
		} else {
			echo "Host: " . $host['name'] . " is not reachable\n";
		}
	}
	fclose($myfile);
}

/**
 * Output OP5 data
 * 
 * @param array $opts
 * 
 * @return string
 */
function get_inventory($opts) {	

	if(!array_key_exists("help", $opts) && (array_key_exists("list", $opts) || array_key_exists("static", $opts))) {
		$data = get_host_list_from_op5(array_key_exists("static_limit", $opts) ? $opts["static_limit"] : null);
		$data = filter_active_hosts($data);
				
		if(array_key_exists("list", $opts)) {
			$ret = parse_host_list_to_ansible_json($data);
		} else if(array_key_exists("static", $opts)) { 
			create_static_inventory_file(
				$data, 
				array_key_exists("static_filename", $opts) ? $opts["static_filename"] : "op5_hosts.ansible", 
				array_key_exists("static_group", $opts) ? $opts["static_group"] : "hosts"
			);
		}
		
	} elseif(!array_key_exists("help", $opts) && array_key_exists("host", $opts)) {
		$data = get_host_from_op5($opts["host"]);
		$data = is_host_active($data);
		$ret = count($data) > 0 && count($data[0]) > 0 ? parse_host_vars_to_ansible_json($data[0][0]) : "{}";
	} else {
		$ret = "Usage: get_op5_inventory.php [OPTION]\n";
		$ret .= "op5-ansible-dynamic-inventory @2018\n\n";
		$ret .= "--list\t\t\t\tget json list of op5 hosts\n";
		$ret .= "--host=HOST\t\t\tget ansible meta variable from op5 host\n";
		$ret .= "--static\t\t\tcreate inventory file from op5 hosts\n";
		$ret .= "--static_group=NAME\t\tstatic inventory group name\n";
		$ret .= "--static_filename=FILENAME\tfilename of static inventory\n";
		$ret .= "--static_limit=LIMIT\t\tlimit number of host for inventory\n";
		$ret .= "--help\t\t\t\tshow this help message\n";
	}
	
	return $ret;
}

$longopts = array(
    "list",
	"host:",
	"static",
	"static_group",
	"static_filename:",
	"static_limit:",
	"help",
);

$opts = getopt("", $longopts);

echo get_inventory($opts);

//echo json_encode($config);