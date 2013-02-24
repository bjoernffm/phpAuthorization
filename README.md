====================
phpAuthorization Installation
====================

.. contents::
..
  
This package offers a really easy to use class for handling multiple users and
of course their passwords in a secure way. This class uses the SHA-512 algorythm
to hash the user-passwords.

Requirements
-------------

* php5 or greater

 * http://php.net/
 
 * 5.3 is the primary test environment.

 * MySQLi must be enabled/activated 

* MySQL 4.1.13 or greater / MySQL 5.0.7 or greater

Installation
------------

Just execute users.sql to create a new user-table and upload
phpAuthorization.php to any folder you like. Now initialize phpAuthorization
with your db-settings and you're done!
