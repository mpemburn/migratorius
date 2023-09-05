# Migratorius

A Laravel/Vue utility to migrate WordPress subsites from one database to another.

## Requirements:
**Migratorius** was created in **Laravel version 9.x** and requires the following:

* **PHP** >= 8.1
* **Composer** >= 2.5.5
* **npm** >= 9.7.2

## Installation:
Install **Migratorius** locally with the following command:

`git clone git@github.com:mpemburn/migratorius.git`

Change to the `migratorius` directory and run:

`composer install`

...to install the PHP dependencies.

`npm install`

...to install modules needed to compile the JavaScript and CSS assets.

`npm run build`

...to do the asset compiling.

Copy `.env.example` to `.env` and make all necessary changes.

You will need to run a web server to run **Migratorius** in a browser.
I recommend [**Laravel Valet**](https://laravel.com/docs/10.x/valet), but you can do it simply by going to the project
directory and running:

`php artisan:serve`

This will launch a server on `http://127.0.0.1:8000`

### Register and Log in
To begin, you will need to create a user account. Click on the "**Register**" link
at the top right side of your browser page.

### Subsite Cloning
In order to use this service, you must have at least one WordPress database installed locally.
The database(s) need to be defined in your `.env` file as follows:

`INSTALLED_DATABASES="Database 1:my_first_db,Database 2:my_second_db"`

The user interface provides two dropdown lists: "**From**" and "**To**".  Select the 
appropriate databases, and the multi-select boxes beneath the dropdown will
populate with all of the subsites (blogs) from each.  You may then choose one
or more subsites.  Clicking on "**Migrate**" will copy all tables from the source
to the destination database.  There is also a filter field that will narrow
the available subsites if you have a large number of these.

