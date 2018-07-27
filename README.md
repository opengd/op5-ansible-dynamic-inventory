# op5-ansible-dynamic-inventory

Script to create a Ansible dynamic inventory from OP5.

## Getting Started

### Prerequisites

To run this script you will need PHP and the PHP cURL module.

```
# Install php curl module for PHP 7.2 on Ubuntu
sudo apt-get install php7.2-curl
```

### Installing

Clone to git rep to get the script and example config file.

```
git clone https://github.com/opengd/op5-ansible-dynamic-inventory.git
```
This will retriev the git repo and you can find the php 

## Usage

op5-ansible-dynamic-inventory script can be config by using a config json file or you can change the config inside the php script file. Using a seperate config file (default: config.json) is recommended.

This is the example config.json, you can find it as config.json.example in the op5-ansible-dynamic-inventory folder. Copy this file and rename it to config.json, and edit the copy to create your own config file.

``` javascript
{
    "OP5":{
        "LIST_QUERIES":[
            {
                "USERPWD": "api$Default:api",
                "HOST": "https:\/\/YOUR.OP5.URL",
                "API": "\/api\/filter\/query?format=json&query=",
                "FILTERS": {
                    "demo": {
                        "FILTER": "[hosts]name~~\"demo*\"",
                        "COLUMNS": { 
                            "NAME": "name",
                            "ADDRESS": "address"
                        },
                        "HOST_VARS":{
                            "ansible_port": 22,
                            "ansible_host": "ADDRESS" 
                        },
                        "LIMIT": null,
                        "OFFSET": null
                    }
                },
                "GROUP_VARS":[]
            }
        ],
        "HOST_QUERIES":[
            {
                "USERPWD":"api$Default:aipi",
                "HOST":"https:\/\/YOUR.OP5.URL",
                "API":"\/api\/filter\/query?format=json&query=",
                "QUERY":"[hosts]name= {HOST}",
                "COLUMNS": { 
                    "NAME": "name",
                    "ADDRESS": "address"
                },
                "VARS":{
                    "ansible_port":[22,22022],
                    "ansible_host":"ADDRESS"
                }
            }
        ]
    }
}
```

Ansible 

## Built With

* [PHP](http://http://php.net) - PHP is a popular general-purpose scripting language that is especially suited to web development.
* [OP5](https://www.op5.com/) - OP5 Monitor
* [Ansible](https://www.ansible.com/) - Ansible automation

## Contributing

Please read [CONTRIBUTING.md](https://gist.github.com/PurpleBooth/b24679402957c63ec426) for details on our code of conduct, and the process for submitting pull requests to us.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
