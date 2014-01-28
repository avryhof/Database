Database
========

A Simple Database abstraction layer.

Normalization
-------------

This library is designed mostly to normailze the usage of the included databases.

Object Oriented
---------------

Enables some non object-oriented databse functions to be accessed and used in an object-oriented way.

K.I.S.S.
--------

This layer is designed so you don't need to learn a whole new way of working with databases. It is designed to work as closely to the built-in PHP functionality as possible, while making it possible to port your application from any of the supported databses to another without changing a bunch of code.


Example
-------
```php
  require_once("database.php");
  
  $db = new Database("mysql://user:pass@localhost/database");
  
  or
  
  $db = new Database("sqlite:///home/user/data/users.db?mode=0666");
  
  /* The Code Below works the same on all supported databases! */
  
  $db->insert("users", array("name" => "User", "password" => "{password}", "email" => "someone@example.com"));
  
  $users = $db->query("SELECT * FROM users WHERE name = 'User'");

  if ($users->num_rows > 0) {
    while($user = $users->fetch_assoc()) {
      echo "<pre>" . print_r($user,true) . "</pre>";
    }
  }
  
  $db->update("users", array("name" => "Bob"),"name = "User");
  
  $db->delete("users", "name = 'Bob'");
```
