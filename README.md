Wordpress Category Protection
======================

This Wordpress plugin allows you to **protect your posts categories with passswords**, removing the need to give a password to each post you want to password protect.


It also works with pages password protection, *if* a page/category plugin is installed like [this one for example](http://wordpress.org/plugins/add-tags-and-category-to-page/)


The plugin makes use of session variables to store password, so users do not have to re-enter passwords for every article/page that is password protected.


This is version 1.0, and I wrote this code in a day, so for now it should be for casual use as the security has not been tested thoroughly.
If you find a bug please let me know or feel free to clone/fix/ask for a pull request


> **Warning** : This does not store the category passwords into the posts or pages, just in the options database table. As a result, if you deactivate this plugin, your posts/pages will be left unprotected.

> Right now the plugin is available in English and French, please feel free to add translations in your own language and ask for a pull request.


> **Knonw issue** : Some themes (or potentially plugins) are outputing HTML outsite the scope of the the_content() and the comments_template() functions.
This plugin will not protect the output outside these functions scope. If you have a solution for this, please let me know!