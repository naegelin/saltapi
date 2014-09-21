saltapi
=======

This is a PHP client for talking to the restful endpoint of Salt Stack. Very basic functionality for illustrative purposes. 

Useage:

```
 $salt = new SaltClient('my.saltapi.com,'443','username','password');
 $results = $salt->run('*','test.ping');
 $results = $salt->jobs($results->jid);
```

Easily add to your projects with composer:

```
	"require": {

		"naegelin/saltapi": "dev-master"
	},

```