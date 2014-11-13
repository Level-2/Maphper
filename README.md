Maphper
=======

Maphper - A php ORM using the Data Mapper pattern


A work in progress!



Features:
--------

1) Creates database tables on the fly

2) Currently supports database datables but will support XML files, web services even twitter feeds as Data Sources

3) Supports relations between any two data sources




Maphper takes a simplistic and minimalist approach to data mapping and aims to provide the end user with an intuitive and easy to use API.

The main philosophy of Maphper is that the programmer should deal with sets of data and then be able to manipulate the set and extract other data from it.

```php
$pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'username', 'password');
$blogSource = new \Maphper\DataSource\Database($pdo, 'blog', 'id');
$blogs = new \Maphper\Maphper($blogSource);
```


This sets up a Data Mapper that maps to the 'blog' table in a MySQL database. Each mapper has a data source. This source could be anything, a database table, an XML file, a folder full fo XML files, a CSV, a Twitter feed, a web service, or anything.

The aim is to give the developer a consistent and simple API which is shared between any of the data sources. 

It's then possible to treat the $blogs object like an array.

You can loop through all the blogs using:

```php
foreach ($blogs as $blog) {
  echo $blog->title . '<br />';
}
```

Any fields from the database table (or xml file, web service, etc) will be available in the $blog object

Alternatively you can find a specific blog using the ID

```php
echo $blog[142]->title;
```

Which will find a blog with the id of 142 and display the title.


The mapper data set can be manipulated with a few methods. For instance, if you only wanted blogs posted on a specific date you could use:

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

This will count the total number of blogs in the table. To count the blogs with a specific categoryId:

```php
//Count the number of blogs in category 3
echo count($blogs->filter(['categoryId' => 3]);
```

Relationships
-------------

Maphper also supports relations. If there was an Author table which stored information about blog authors, it could be set up in a similar way: 

```php
$authorSource = new \Maphper\DataSource\Database($pdo, 'author');
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
$relation = new \Maphper\Relation(\Maphper\Relation::ONE, $authors, 'authorId', 'id');
$blogs->addRelation('author', $relation);
```

\Maphper\Relation::ONE tells Maphper that it's a one-to-one relationship

The second parameter, $authors tells Maphper that the relationship is to the $authors mapper (in this case, the author database table, although you can define relationships between different data types)

'authorId' is the field in the blog table that's being joined from

'id' is the field in the author table that's being joined to

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
//Create a one-to-one relationship between blogs and authors (a blog can only have one author)
$relation = new \Maphper\Relation(\Maphper\Relation::MANY, $blogs, 'id', 'authorId');
$blogs->addRelation('author', $relation);
```

This is creating a relationsip between authors and blogs using the id field in the $authors mapper to the authorId field in the $blogs mapper and making a blogs property available in an object returned by the $authors mapper;

```php
//Count all the blogs by the author with id 5
$authors[4]->id . ' has posted ' .  count($authors[4]->blogs)  . ' blogs:<br />';

//Loop through all the blogs created by the author with id 3
foreach ($authors[4]->blogs as $blog) {
    echo $blog->title . '<br />';
}
```
