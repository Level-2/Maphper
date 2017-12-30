Maphper
=======

Maphper - A php ORM using the Data Mapper pattern


A work in progress!


[![Scrutinizer-CI](https://scrutinizer-ci.com/g/Level-2/Maphper/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Level-2/Maphper/)


Features:
--------

1) Creates database tables on the fly

2) Currently supports database tables but will support XML files, web services even twitter feeds as Data Sources

3) Supports relations between any two data sources

4) Composite primary keys


Maphper takes a simplistic and minimalist approach to data mapping and aims to provide the end user with an intuitive and easy to use API.

The main philosophy of Maphper is that the programmer should deal with sets of data and then be able to manipulate the set and extract other data from it.


Basic Usage
----------

This sets up a Data Mapper that maps to the 'blog' table in a MySQL database. Each mapper has a data source. This source could be anything, a database table, an XML file, a folder full of XML files, a CSV, a Twitter feed, a web service, or anything.

The aim is to give the developer a consistent and simple API which is shared between any of the data sources. 

It's then possible to treat the $blogs object like an array.


To set up a Maphper object using a Database Table as its Data Source, first create a standard PDO instance:

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
```

Then create an instance of \Maphper\DataSource\DataBase passing it the PDO instance, name of the table and primary key:

```php
$blogSource = new \Maphper\DataSource\Database($pdo, 'blog', 'id');
```

Finally create an instance of \Maphper\Maphper and pass it the data source:

```php
$blogs = new \Maphper\Maphper($blogSource);
```

You can loop through all the blogs using:

```php
foreach ($blogs as $blog) {
  echo $blog->title . '<br />';
}
```

Any fields from the database table (or xml file, web service, etc) will be available in the $blog object

Alternatively you can find a specific blog using the ID

```php
echo $blogs[142]->title;
```


Which will find a blog with the id of 142 and display the title.


Filters
-------

Maphper supports filtering the data:

```php
//find blogs that were posted on a specific date
$filteredBlogs = $blogs->filter(['date' => '2014-04-09']);

//this will only retrieve blogs that were matched by the filter
foreach ($filteredBlogs as $blog) {
	echo $blog->title;
}
````

Filters can be extended and chained together:



```php
//find blogs that were posted with the title "My Blog" by the author with the id of 7
$filteredBlogs = $blogs->filter(['title' => 'My Blog'])->filter(['authorId' => 7]);

//this will only retrieve blogs that were matched by both filters
foreach ($filteredBlogs as $blog) {
	echo $blog->title;
}
````


As well as filtering there are both sort() and limit() methods which can all be chained:

```php
foreach ($blogs->filter(['date' => '2014-04-09']) as $blog) {
  echo $blog->title;
}
```

To find the latest 5 blogs you could use:

```php
foreach ($blogs->limit(5)->sort('date desc') as $blog) {
  echo $blog->title;
}
```


Like any array, you can count the total number of blogs using:

```php
echo 'Total number of blogs is ' . count($blogs);
```

This will count the total number of blogs in the table. You can also count filtered results:

```php
//Count the number of blogs in category 3
echo count($blogs->filter(['categoryId' => 3]);
```

Saving Data
-----------

To save an object back into the data mapper, simply create an instance of stdClass:

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
$blogSource = new \Maphper\DataSource\Database($pdo, 'blog', 'id');
$blogs = new \Maphper\Maphper($blogSource);


$blog = new stdClass;

$blog->title = 'My Blog Title';
$blog->content = 'This is my first blog entry';

//Store the blog using the next available ID
$blogs[] = $blog;

echo 'The new blog ID is :' . $blog->id;

``` 


Alternatively, you can write a record to a specific ID by specifying the index:

```php
$blog = new stdClass;

$blog->title = 'My Blog Title';
$blog->content = 'This is my first blog entry';

//Store the blog with the primary key of 7
$blogs[7] = $blog;

```

Note: The behaviour of this is identical to setting the id property on the $blog object directly:

```php

$blog = new stdClass;

$blog->id = 7;
$blog->title = 'My Blog Title';
$blog->content = 'This is my first blog entry';

//Store the blog with the primary key of 7
$blogs[] = $blog;

```


Relationships
-------------

If there was an `author` table which stored information about blog authors, it could be set up in a similar way: 

```php
$authorSource = new \Maphper\DataSource\Database($pdo, 'author', 'id');
$authors = new \Maphper\Maphper($authorSource);
```


This could be used similarly:

```php
$author = $authors[123];
echo $author->name;
```

Once both the blogs and authors mappers have been defined, you can create a relationship between them.


This is a one-to-one relationship (one blog has one author) and can be achieved using the addRelation method on the blog mapper:

```php
//Create a one-to-one relationship between blogs and authors (a blog can only have one author)
$relation = new \Maphper\Relation\One($authors, 'authorId', 'id');
$blogs->addRelation('author', $relation);
```

Using an instance of \Maphper\Relation\One tells Maphper that it's a one-to-one relationship

The first parameter, `$authors` tells Maphper that the relationship is to the `$authors` mapper (in this case, the author database table, although you can define relationships between different data sources)

`authorId` is the field in the blog table that's being joined from

`id` is the field in the author table that's being joined to

After the relation is constructed it can be added to the blog mapper using:

```php
$blogs->addRelation('author', $relation);
```

The first parameter (here: 'author') is the name of the property the related data will be available under. So any object retrieved from the $blogs mapper will now have an 'author' property that contains the author
of the blog and can be used like this:

```php
foreach ($blogs as $blog) {
  echo $blog->title . '<br />';
  echo $blog->author->name . '<br />';
}
```


Similarly, you can define the inverse relation between authors and blogs. This is a one-to-many relationship because an author can post more than one blog.



```php
//Create a one-to-many relationship between blogs and authors (an author can have multiple blog entries)
//Joining from the 'id' field in the authors mapper to the 'authorId' field in the blogs mapper
$relation = new \Maphper\Relation\Many($blogs, 'id', 'authorId');
$authors->addRelation('blogs', $relation);
```

This is creating a One:Many relationship between the `$authors` and `$blogs` mappers using the `id` field in the `$authors` mapper to the `authorId` field in the `$blogs` mapper and making a `blogs` property available for any object returned by the `$authors` mapper.

```php
//Count all the blogs by the author with id 4
$authors[4]->name . ' has posted ' .  count($authors[4]->blogs)  . ' blogs:<br />';

//Loop through all the blogs created by the author with id 4
foreach ($authors[4]->blogs as $blog) {
    echo $blog->title . '<br />';
}
```

Saving values with relationships
--------------------------------

Once you have created your mappers and defined the relationships between them, you can write data using the relationship. This will automatically set any related fileds behind the scenes.


```php
$authors = new \Maphper\Maphper(new \Maphper\DataSource\Database($pdo, 'author'));
$blogs = new \Maphper\Maphper(new \Maphper\DataSource\Database($pdo, 'blog', 'id'));
$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));


$blog = new stdClass;
$blog->title = 'My First Blog';
$blog->date = new \DateTime();
$blog->author = new stdClass;
$blog->author->name = 'Tom Butler';

$blogs[] = $blog;
```

This will save both the `$blog` object into the `blog` table and the `$author` object into the `author` table as well as setting the blog record's authorId column to the id that was generated.


You can also do the same with one-to-many relationships:


```php
$authors = new \Maphper\Maphper(new \Maphper\DataSource\Database($pdo, 'author'));
$blogs = new \Maphper\Maphper(new \Maphper\DataSource\Database($pdo, 'blog', 'id'))

$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));


//Find the author with id 4
$author = $authors[4]; 


$blog = new stdClass;
$blog->title = 'My New Blog';
$blog->date = new \DateTime();

//Add the blog to the author. This will save to the database at the this point, you do not need to explicitly 
//Save the $author object after adding a blog to it.
$author->blogs[] = $blog;
```


Composite Primary Keys
----------------------

Maphper allows composite primary keys. For example, if you had a table of products you could use the manufacturer id and manufacturer part number as primary keys (Two manufacuterers may use the same part number)

To do this, define the data source with an array for the primary key:

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
$productSource = new \Maphper\DataSource\Database($pdo, 'products', ['manufacturerId', 'partNumber']);
$products = new \Maphper\Maphper($productSource);
```

Once you have defined the source to use multiple keys, you can treat the `$products` variable like a two dimensional array:

```php
//Get the product with manufacturerId 7 and partNumber AC294
echo $products[7]['AC294']->name;
```

To write data using composite keys, you simply write an object to a specified index:

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
$productSource = new \Maphper\DataSource\Database($pdo, 'products', ['manufacturerId', 'partNumber']);
$products = new \Maphper\Maphper($productSource);

$product = new stdClass;
$product->name 'Can of cola';

$products[1]['CANCOLA'] = $product;

```


Dates
-----

Maphper uses the inbuilt PHP `\DateTime` class to store and search by dates:

```php
$blog = new stdClass;
$blog->title = 'A blog entry';

//You can construct the date object using any of the formats availble in the inbuilt PHP datetime class
$blog->date = new \DateTime('2015-11-14');

```


This can also be used in filters:


```php
//Find all blogs posted on 2015-11-14
$maphper->filter(['date' => new \DateTime('2015-11-14')]);

```

*It is recommended to use the DateTime class rather than passing the date value as a string as not all mappers will use the same internal date format* 


Automatic Database Table creation/amendment
-------------------------------------------

Maphper can be instructed to automatically construct database tables. This is done on the fly, you just need to tell maphper you want to use this behaviour. When you construct your Database Data source, set editmode to true:

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
$blogs = new \Maphper\DataSource\Database($pdo, 'blogs', 'id', ['editmode' => true]);
```

n.b. `'editmode => true`' is shorthand for \Maphper\DataSoruce\Database::EDIT_STRUCTURE | \Maphper\DataSoruce\Database::EDIT_INDEX | \Maphper\DataSoruce\Database::EDIT_OPTIMISE;

The available flags can be interchanged using bitwise or e.g `\Maphper\DataSoruce\Database::EDIT_STRUCTURE | \Maphper\DataSoruce\Database::EDIT_INDEX` will enable structure and index modification but will not allow column optimisation.

The three options for `editmode` are:

`\Maphper\DataSoruce\Database::EDIT_STRUCTURE` - When this is set, Maphper automatically creates tables that don't exist, creates columns when writing to properties that don't yet exist and changes data types on out-of-bounds columns:


```php
$blog = new stdClass;
$blog->title = 'A blog';
$blog->date = new \DateTime();
$blogs[] = $blog;
```

For database mappers, this will issue a `CREATE TABLE` statement that creates a table called `blogs` with the columns `id INT auto_increment`, `title VARCHAR`, `date DATETIME`.

### Type juggling

Maphper will use the strictest possible type when creating a table. For instance:


```php
$blog = new stdClass;
$blog->title = 1;
$blogs[] = $blog;
```

This would create a `title` column as an integer because only an integer has been stored in it. However, if another record was added to the table after it was created with a different type:

```php

$blog = new stdClass;
$blog->title = 'Another blog';
$blogs[] = $blog;
```

This would issue an `ALTER TABLE` query and change the `title` colum to `varchar`. Similarly if a very long string was added as the title the column would be changed to `LONGBLOG`. This is all done on the fly and behind the scenes, as the developer you don't need to worry about the table structure at all.


### Indexes

`\Maphper\DataSoruce\Database::EDIT_INDEX` When this is set, Maphper will automatically add indexes to columns used in WHERE, ORDER and GROUP statements. If a mutli-column where is done, a multi-column index is also added.

### Database optimisation

`\Maphper\DataSoruce\Database::EDIT_OPTIMISE` when this is set, Maphper automatically periodically optimises database tables. For example, a column set to VARCHAR(255) where the longest entry is 7 characters will be changed to VARCHAR(7) or a VARCHAR(255) column that has 3 records with values 1,2,3 will be converted to INT(11). This will also automatically delete any columns that have NULL in every record.

Currently optimisation happens once every 500 times the DataSource is created. In future versions this value will be configurable.

Concrete classes for mapped objects
-----------------------------------

It's possible to use your own classes instead of stdClass for any object managed by Maphper. This object will automatically have its properties set when it is created.

For example, if you had a product class:

```php
class Product {
	private $name;
	private $price;
	
	const TAX_RATE = 0.2;
	
	public function getTax() {
		return $this->price * self::TAX_RATE;
	}
	
	public function getTotalPrice() {
		return $this->price + $this->getTax();
	}
	
	public function setName($name) {
		$this->name = $name;
	}
}
```


You can instruct Maphper to use this class using the `resultClass` option in the `$options` array when creating the Maphper instance:

```php
$dataSource = new \Maphper\DataSource\Database($pdo, 'product', 'id');
$products = new \Maphper\Maphper($dataSource, ['resultClass' => 'Product']);

$product = $products[123];


echo get_class($product); //"Product" instance

//And as expected, the methods from Product are available:
$tax = $product->getTax();
$total = $product->getTotalPrice();
```

Private properties are both saved and loaded as any normal properties.

Similarly, you can create an instance of the `Product` class and save it to the mapper.

```php
$product = new Product;
$product->setName('A Product');
//Write the product to the mapper. Even though $product->name is private it will still be stored
$products[] = $product;
```

### Factory creation for new objects

Sometimes your result class may have dependencies. In this case, you can specify a method rather than a class name to act as a factory. Consider the following:

```php

class TaxCalculator {
	const TAX_RATE = 0.2;
	
	public function getTax($price) {
		return $price * self::TAX_RATE;
	}
}

class Product {
	private $name;
	private $price;
	private $taxCalculator;
	
	
	public function __construct(TaxCalculator $taxCalculator) {
		$this->taxCalculator = $taxCalculator;
	}	
	
	public function getTax() {
		return $this->taxCalculator->getTax($this->price);
	}
	
	public function getTotalPrice() {
		return $this->price + $this->getTax();
	}
}
```
 
In this case using:

```php
$dataSource = new \Maphper\Maphper($database, ['resultClass' => 'Product']);
```

Will error, because when an instance of `Product` is constructed, Maphper isn't smart enough to guess that a `TaxCalculator` instance is required as a constructor argument. Instead, you can pass a closure that returns a fully constructed object:


```php
$taxCalculator = new TaxCalculator;

$dataSource = new \Maphper\Maphper($database, ['resultClass' => function() use ($taxCalculator) {
	return new Product($taxCalculator);
}]);
```

Alternatively if you want considerably more control over the dependencies you can use a Dependency Injection Container such as [Dice](https://r.je/dice.html):

```php
$dice = new \Dice\Dice;

$dataSource = new \Maphper\Maphper($database, ['resultClass' => function() use ($dice) {
	return $dice->create('Product');
}]);
````



## Many to Many relationships

Consider tables `movie` and `actor` an actor can be in more than one movie and a movie has more than one actor it's impossible to model the relationship with just two tables using primary/foregin keys.

In relational databases (and Maphper) this requires an intermediate table that stores the `actorId` and the `movieId`.

To model this relationship using Maphper first set up the standard `actor` and `movie` mappers:

```php
$actors = new \Maphper\Maphper(new \Maphper\Datasource\Database($pdo, 'actor', 'id'));
$movies = new \Maphper\Maphper(new \Maphper\Datasource\Database($pdo, 'movie', 'id'));
```

Then add a table for the intermediate table. Note this requires two primary keys, one for the `actorId` and one for the `movieId`

```php
$cast = new \Maphper\Maphper(new \Maphper\Datasource\Database($pdo, 'cast', ['movieId', 'actorId']));
```

*Note that Maphper can, of course, create this table for you with editmode turned on*

Now it's possible to set up Many to Many relationships using the `\Maphper\Relation\ManyMany` class as the relationship. Firstly for the actor to movie relationship:

```php
$actors->addRelation('movies', new \Maphper\Relation\ManyMany($cast, $movies, 'id', 'movieId'));
```

This creates a relationship on `actor` objects called `movies`. 

The first constructor argument for the ManyMany class is the intermediate table. 

The second constructor argument is the table being mapped to. 

The third is the primary key of the `movies` table.

The fourth is the key in the intermeidate table.

This joins the $actors mapper to the $cast mapper and then the $cast mapper to the $movies mapper.

Once this is done, you can also set up the inverse relationship, mapping movies to the actors that starred in them in the same way:

```php
$movies->addRelation('actors', new \Maphper\Relation\ManyMany($cast, $actors, 'id', 'actorId'));
```


Once both of these relationships are set up you can add an actor with movies:

```php

$actor = new \stdclass;
$actor->id = 123;
$actor->name = 'Samuel L. Jackson';

//save the actor
$actors[] = $actor;


//now add some movies to the actor
$movie1 = new \stdclass;
$movie1->title = 'Pulp Fiction';

$actor->movies[] = $movie1;


//now add some movies to the actor
$movie2 = new \stdclass;
$movie2->title = 'Snakes on a Plane';

$actor->movies[] = $movie2;

```

once this is done you can get all movies played by an actor using:

```php

$actor = $actors[123];

echo $actor->name . ' was in the movies:' . "\n";

foreach ($actor->movies as $movie) {
	echo $movie->title . "\n";
}
``` 

Which will print:

```
Samuel L. Jackson was in the movies:
Pulp Fiction
Snakes on a Plane
```

Of course that's possible with a normal one to many relationship. A many to many relationship is only useful when there are more actors:


```php
$actor = new \stdclass;
$actor->id = 124;
$actor->name = 'John Travolta';
$actors[] = $actor;

//Find the movie 'Pulp Fiction and add it to John Travolta
$movie = $movies->filter(['title' =>'Pulp Fiction'])->item(0);
$actor->movies[] = $movie;

```

Now it's possible to find all the actors who were in Pulp Fiction using:


```php
$movie = $movies->filter(['title' =>'Pulp Fiction'])->item(0);

echo 'The actors in ' . $movie->title . ' are :' . "\n";
foreach ($movie->actors as $actor) {
	echo $actor->name . "\n";
}
```

Which prints

```
The actors in Pulp Fiction are:
Samuel L. Jackson
John Travolta
```




### Storing data in the intermediate table

Sometimes it's useful to store extra information in the intermediate table. In the example above, it would be nice to know the name of the character the actor played in a given movie. For this, there are two extra fields used when setting up the relationship.

Instead of 

```php
$actors->addRelation('movies', new \Maphper\Relation\ManyMany($cast, $movies, 'id', 'movieId'));
$movies->addRelation('actors', new \Maphper\Relation\ManyMany($cast, $actors, 'id', 'actorId'));
```

You can use:

```php
$actors->addRelation('roles', new \Maphper\Relation\ManyMany($cast, $movies, 'id', 'movieId', 'movie');
$movies->addRelation('cast', new \Maphper\Relation\ManyMany($cast, $actors, 'id', 'actorId', 'actor');
```

The 5th argument to the  `\Maphper\Relation\ManyMany` constructor have been added.

This argument is the name of the field to use on objects from the intermediate table. When this is supplied, the intermediate mapper is not traversed automatically, instead it is accessed like a normal one:many relationship between the parent table (e.g. actors) and the intermediate table e.g. cast.

In this example, actors have `roles` and movies have a `cast`. Now that this is set up it's possible to assign some roles to an actor:


```php
$actor = $actors[123];


//Find the movie 
$movie = $movies->filter(['title' =>'Pulp Fiction'])->item(0);

//Create a role
$role = new \stdClass;
//Set the character name for the role
$role->characterName = 'Jules Winnfield';
//Assign the movie to the role
$role->movie = $movie;
//Assign the role to the actor
$actor->roles[] = $role;


//Find the movie 
$movie = $movies->filter(['title' =>'Snakes on a Plane'])->item(0);
//Create a role
$role = new \stdClass;
//Set the character name for the role
$role->characterName = 'Neville Flynn';
//Assign the movie to the role
$role->movie = $movie;
//Assign the role to the actor
$actor->roles[] = $role;


```

This has assigned the movies Pulp Fiction and Snakes on a Plane to Samuel L. Jackson via the 'role' attribue. It's now possible to show the movies:


```php
$actor = $actors[123];

echo $actor->name . ' has the roles:' . "\n"
foreach ($actors[123]->roles as $role) {
    echo $role->characterName . ' in the movie . ' . $role->movie->title . "\n";
}
```

Which will print out:

```php
Samuel L. Jackson has the roles:
Jules Winnfield in Pulp Fiction
Neville Flynn in Snakes on a plane
```
