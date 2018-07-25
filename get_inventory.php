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
						"LIMIT" => null,
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
				"QUERY" => [
					'?format=json&query=[hosts]name= ',
					"COLUMNS" => "&columns=name,address",
					"VARS" => [
						"ansible_port" => [
							22, 22022
						],
						"ansible_host" => 'address'
					],
				]
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
	$call =  OP5_API_GET_HOSTS_QUERY . OP5_API_QUERY_COLUMNS;
	$call .= $static_limit ? "&limit=" . $static_limit : "";
	$call .= !$static_limit && OP5_HOST_LIMIT ? "&limit=" . OP5_HOST_LIMIT : "";
	$call .= OP5_HOST_OFFSET ? "&offset=" . OP5_HOST_OFFSET : "";
	$call = str_replace(" ", "%20", $call);
	$a_handle = curl_init(OP5_API_ADDRESS . $call);
	curl_setopt($a_handle, CURLOPT_USERPWD, OP5_API_USERPWD);
	curl_setopt($a_handle, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($a_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($a_handle, CURLOPT_SSL_VERIFYPEER, false);

	$result = curl_exec($a_handle);
	$data = json_decode($result, true);

	return $data;
}

/**
 * Get a host from OP5 as JSON
 * 
 * @return array
 */
function get_host_from_op5($host) {
	$call =  OP5_API_GET_HOST_QUERY . '"' . $host . '"' . OP5_API_QUERY_COLUMNS;
	$call = str_replace(" ", "%20", $call);
	$a_handle = curl_init(OP5_API_ADDRESS . $call);
	curl_setopt($a_handle, CURLOPT_USERPWD, OP5_API_USERPWD);
	curl_setopt($a_handle, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($a_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($a_handle, CURLOPT_SSL_VERIFYPEER, false);

	$result = curl_exec($a_handle);
	$data = json_decode($result, true);

	return $data;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $hosts
 * 
 * @return array
 */
function filter_active_hosts($hosts) {
	$filter_hosts = array();

	if($hosts) {
		for($i = 0; $i < count($hosts); $i++) {
			foreach(ANSIBLE_PORTS as $port) {
				if ($fp = @fsockopen($hosts[$i]['address'], $port, $errno, $errstr, 1)) { 
					$hosts[$i]['ansible_port'] = $port;
					array_push($filter_hosts, $hosts[$i]);
					fclose($fp);
					break;
				}
			}
		}
	}

	return $filter_hosts;
}

/**
 * Parse OP5 host list to a ansible json string
 * 
 * @param array $hosts
 * 
 * @return string
 */
function parse_host_list_to_ansible_json($hosts, $group = "hosts") {
	$main = array(
		$group => array("hosts" => array()),
		"_meta" => array("hostvars" => array())
	);
	foreach($hosts as $host) {
		array_push($main[$group]["hosts"], $host['name']);
		$main["_meta"]["hostvars"][$host['name']] = array(
			'ansible_port' => $host['ansible_port'],
			'ansible_host' => $host['address'],
		);
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
		$data = filter_active_hosts($data);
		$ret = count($data) > 0 ? parse_host_vars_to_ansible_json($data[0]) : "{}";
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

echo json_encode($config);