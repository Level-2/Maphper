<?php
use Maphper\Maphper;
class MockDatasourceTest extends PHPUnit_Framework_TestCase {
    protected function getMaphper($storage, $primaryKey = 'id', $options = []) {
		$datasource = new \Maphper\DataSource\Mock($storage, $primaryKey);
        return new \Maphper\Maphper($datasource, $options);
	}

    public function testSave() {
		$storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');

		$blog = new stdclass;
		$blog->id = 12;
		$blog->title = md5(uniqid('foo'));

		$mapper[] = $blog;

		//Check it's been saved in the mapper
		$this->assertTrue(count($mapper) == 1);

		//Of course that doesn't mean it's also been stored in the database correctly...
		$result = $storage[12];

		$this->assertEquals($result->title, $blog->title);

	}

    public function testSaveNull() {
        $storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');

		$blog = new stdclass;
		$blog->id = 4;
		$blog->title = null;
		$mapper[] = $blog;

		$result = $storage[4];

		$this->assertEquals(null, $result->title);

	}


	public function testSetToNull() {
        $storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');

		$blog = new stdclass;
		$blog->id = 4;
		$blog->title = 'A title';
		$mapper[] = $blog;


		$result = $storage[4];

		//Check the title was set originally correctly
		$this->assertEquals($result->title, $blog->title);

		$blog->title = null;
		$mapper[] = $blog;


		$result = $storage[4];

		//Now see if it's been correctly updated to NULL
		$this->assertEquals(null, $result->title);
	}

	public function testNotExists() {
        $storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');
		$this->assertFalse(isset($mapper[99]));
	}


	public function testObjectGraphSaveOne() {
        $bStorage = new ArrayObject();
        $aStorage = new ArrayObject();

		$blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));


		$author = new stdClass;
		$author->name = 'Blog Author';

		$blog = new stdclass;
		$blog->author = $author;
		$blog->title = 'Blog 1';


		$this->assertEquals(0, count($blogs));
		$this->assertEquals(0, count($authors));
		$blogs[] = $blog;

		$this->assertEquals(1, count($blogs));
		$this->assertEquals(1, count($authors));
	}

    public function testObjectGraphSaveMany() {
        $bStorage = new ArrayObject();
        $aStorage = new ArrayObject();

		$blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));

		$author = new stdClass;
		$authors[] = $author;
		$author->name = 'Blog Author';

		//$author->blogs = [];

		$blog1 = new stdclass;
		$blog1->name = 'Blog 1';

		$author->blogs[] = $blog1;


		$blog2 = new stdclass;
		$blog2->name = 'Blog 2';
		$author->blogs[] = $blog2;


		//This should now save the author object and the two blog objects.
		$authors[] = $author;

		$this->assertEquals(2, count($blogs));
		$this->assertEquals(1, count($authors));

		unset($authors);
		$authors = $this->getMaphper($aStorage, 'id');
		$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));

		$this->assertEquals(2, count($authors[1]->blogs));
	}




	public function testObjectGraphSaveDeep() {

        $bStorage = new ArrayObject();
        $aStorage = new ArrayObject();
        $cStorage = new ArrayObject();

		$blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');
        $comments = $this->getMaphper($cStorage, 'id');

		$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));

		$blogs->addRelation('comments', new \Maphper\Relation\Many($comments, 'id', 'blogId'));


		$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));

		$author = new stdClass;
		$author->name = 'Blog Author';
		$author->blogs = [];

		$blog = new stdclass;
		$blog->author = $author;
		$blog->title = 'Blog 1';
		$author->blogs[] = $blog;

		$blog2 = new stdclass;
		$blog2->author = $author;
		$blog2->title = 'Blog 2';
		$author->blogs[] = $blog2;

		$authors[] = $author;


		$this->assertEquals(2, count($blogs));
		$this->assertEquals(1, count($authors));

	}

    private function populateBlogs($storage) {

		$mapper = $this->getMaphper($storage, 'id');
		for ($i = 0; $i < 20; $i++) {
			$blog = new stdClass;
			$blog->title = 'blog number ' . $i;
			$blog->rating = 5;

			$blog->date = new \DateTime('2015-01-0' . ($i % 5 + 1));
			$mapper[] = $blog;
		}
		$this->assertEquals(20, count($mapper));
	}

    public function testFindById() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');

		$blog = $mapper[8];
		$this->assertEquals($blog->title, 'blog number 8');

	}

	public function testPkWrite() {
		$blog = new Blog;
		$blog->title = 'Test Write By PK';

        $storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');

		$mapper[7] = $blog;

		$result = $storage[7];

		$this->assertEquals($result->title, $blog->title);
	}

	public function testLoop() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id');
		$iterations = 0;
		foreach ($blogs as $blog) {
			$iterations++;
			$this->assertEquals($blog->title, 'blog number ' . $blog->id);
		}

		//$this->assertEquals(20, $iterations);
	}

	public function testSort() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id');

		$blogs = $blogs->sort('id desc')->getIterator();
		$blogs->rewind();
		$blog = $blogs->current();
		$this->assertEquals('blog number 19', $blog->title);
	}

	public function testLimit() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id');

		$blogs = $blogs->limit('5');
		$iterations = 0;
		foreach ($blogs as $blog) {
			$iterations++;
		}

		$this->assertEquals(5, $iterations);
	}

	public function testOffset() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id');

		$blogs = $blogs->offset(5)->getIterator();

		$blogs->rewind();
		$this->assertEquals(5, $blogs->current()->id);

	}

	public function testInvalidFind() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id');
		$blogs = $blogs->filter(['unknownColumn' => 20]);
		$this->assertEquals(count($blogs), 0);
	}

	public function testResultClassExisting() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id', ['resultClass' => 'blog']);

		foreach ($blogs as $blog) {
			$this->assertInstanceOf('blog', $blog);
		}
	}

	/*
	 * Checks to see if maphper can successfuly write to private properties (It should!)
	 */
	public function testResultClassPrivateProperties() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id', ['resultClass' => 'BlogPrivate']);

		$blog = $blogs[2];
		$this->assertInstanceOf('BlogPrivate', $blog);
		$this->assertEquals('blog number 2', $blog->getTitle());
	}

	public function testResultClassPrivatePropertiesWrite() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id', ['resultClass' => 'BlogPrivate']);

		$blog = $blogs[2];

		$blog->setTitle('Title Updated');

		$blogs[] = $blog;

		//Reload the mapper to ensure no cacheing is used
		unset($blogs);
		unset($blog);
		$blogs = $this->getMaphper($storage, 'id', ['resultClass' => 'BlogPrivate']);

		$blog = $blogs[2];

		$this->assertEquals('Title Updated', $blog->getTitle());
	}


	public function testLoopKey() {
        $storage = new ArrayObject();
        $this->populateBlogs($storage);
		$blogs = $this->getMaphper($storage, 'id', ['resultClass' => 'blog']);

		foreach ($blogs as $id => $blog) {
			$this->assertEquals($id, $blog->id);
		}
	}

	public function testMultiPk() {
		$storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, ['id1', 'id2']);
		//$this->assertTrue(count($mapper) == 0);
		$blog = new stdclass;
		$blog->id1 = 12;
		$blog->id2 = 1024;

		$title = md5(uniqid());
		$blog->title = $title;
		$mapper[] = $blog;

		$b2 = $mapper[12][1024];


		$this->assertEquals($title, $b2->title);
	}

	public function testMultiPk2() {
        $storage = new ArrayObject();
		$mapper = $this->getMaphper($storage, ['id1', 'id2', 'id3', 'id4']);
		//$this->assertTrue(count($mapper) == 0);
		$blog = new stdclass;
		$blog->id1 = 12;
		$blog->id2 = 1024;
		$blog->id3 = 3;
		$blog->id4 = 4;

		$title = md5(uniqid());
		$blog->title = $title;
		$mapper[] = $blog;


		$b2 = $mapper[12][1024][3][4];


		$this->assertEquals($title, $b2->title);
	}

	public function testMultiPkWrite() {
        $storage = new ArrayObject();
        $pk = ['id1', 'id2'];
		$mapper = $this->getMaphper($storage, $pk);

		$blog = new Blog;
		$blog->title = 'ABC';

		$mapper[13][15] = $blog;

        $values = ['id1' => 13, 'id2' => 15];

		$result = array_reduce(iterator_to_array($storage->getIterator()), function ($carry, $current) use ($values, $pk) {
            if ($carry !== null) return $carry;
            $matches = true;
            foreach ($pk as $key) {
                $matches = ($current->$key === $values[$key]) && $matches;
            }
            if ($matches) return $current;
            else return null;
        });

		$this->assertEquals($result->title, $blog->title);
	}

	public function testMultiPkWrite2() {
        $storage = new ArrayObject();
        $pk = ['id1', 'id2', 'id3', 'id4'];
		$mapper = $this->getMaphper($storage, $pk);

		$blog = new Blog;
		$blog->title = 'ABC123';


		$mapper[13][15][3][4] = $blog;

        $values = ['id1' => 13, 'id2' => 15, 'id3' => 3, 'id4' => 4];

        $result = array_reduce(iterator_to_array($storage->getIterator()), function ($carry, $current) use ($values, $pk) {
            if ($carry !== null) return $carry;
            $matches = true;
            foreach ($pk as $key) {
                $matches = ($current->$key === $values[$key]) && $matches;
            }
            if ($matches) return $current;
            else return null;
        });

		$this->assertEquals($result->title, $blog->title);
	}

	public function testMultiPkOffsetExists() {
        $storage = new ArrayObject();
		$pk = ['id1', 'id2', 'id3', 'id4'];
		$mapper = $this->getMaphper($storage, $pk);

		$blog = new Blog;
		$blog->title = 'ABC123';


		$mapper[13][15][3][4] = $blog;

		$this->assertTrue(isset($mapper[13][15][3][4]));

		//And check that it returns false when there is no record under that PK
		$this->assertFalse(isset($mapper[13][15][3][2]));
		$this->assertFalse(isset($mapper[12][15][3][4]));
	}


	public function testMultiPkUnset() {
        $storage = new ArrayObject();
        $pk = ['id1', 'id2', 'id3', 'id4'];
		$mapper = $this->getMaphper($storage, $pk);

		$blog = new Blog;
		$blog->title = 'ABC123';


		$mapper[13][15][3][4] = $blog;

        $values = ['id1' => 13, 'id2' => 15, 'id3' => 3, 'id4' => 4];

		$result = array_reduce(iterator_to_array($storage->getIterator()), function ($carry, $current) use ($values, $pk) {
            if ($carry !== null) return $carry;
            $matches = true;
            foreach ($pk as $key) {
                $matches = ($current->$key === $values[$key]) && $matches;
            }
            if ($matches) return $current;
            else return null;
        });

		$this->assertEquals($result->title, $blog->title);


		unset($mapper[13][15][3][4]);

		$result = array_reduce(iterator_to_array($storage->getIterator()), function ($carry, $current) use ($values, $pk) {
            if ($carry !== null) return $carry;
            $matches = true;
            foreach ($pk as $key) {
                $matches = ($current->$key === $values[$key]) && $matches;
            }
            if ($matches) return $current;
            else return null;
        });

		$this->assertEquals(0, count($result));

	}

    private function populateBlogsAuthors($bStorage, $aStorage) {
		$blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$authorList = [];
		$authorList[0] = new stdClass;
		$authorList[0]->id = 1;
		$authorList[0]->name  = 'Author 1';

		$authorList[1] = new stdClass;
		$authorList[1]->id = 2;
		$authorList[1]->name  = 'Author 2';

		for ($i = 0; $i < 20; $i++) {
			$blog = new stdclass;
			$blog->title = 'Blog number ' . $i;
			$blog->authorId = $authorList[($i % 2)]->id;
			$blogs[] = $blog;
		}


		foreach ($authorList as $author) {
			$authors[] = $author;
		}

	}

    public function testFetchRelationOne() {
        $bStorage = new \ArrayObject();
        $aStorage = new \ArrayObject();
		$this->populateBlogsAuthors($bStorage, $aStorage);

        $blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$relation = new \Maphper\Relation\One( $authors, 'authorId', 'id');
		$blogs->addRelation('author', $relation);


		$blog2 = $blogs[1];

		$this->assertNotEquals($blog2->author, null);
		$this->assertEquals($blog2->authorId, $blog2->author->id);
		$this->assertEquals('Author 2', $blog2->author->name);
	}


	public function testFetchRelationMany() {
        $bStorage = new \ArrayObject();
        $aStorage = new \ArrayObject();
		$this->populateBlogsAuthors($bStorage, $aStorage);

        $blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));
		$author2 = $authors[2];

		//There were 20 blogs spread equally between 2 authors so this author should have 10 blogs
		$this->assertEquals(count($author2->blogs), 10);
		$this->assertNotEquals(null, $author2->blogs->item(0)->title);
	}


	public function testStoreRelatedObjectOne() {
        $bStorage = new \ArrayObject();
        $aStorage = new \ArrayObject();
		$this->populateBlogsAuthors($bStorage, $aStorage);

        $blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));


		$blog2 = $blogs[1];

		$this->assertEquals('Author 2', $blog2->author->name);

		$author3 = new stdclass;
		$author3->id = 3;
		$author3->name = 'Author 3';

		$blog2->author = $author3;

		//Save the blog with the new author
		$blogs[] = $blog2;


		//Get a new instance of the mapper to avoid any caching
		$blogs = $this->getMaphper($bStorage, 'id');
		$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));


		$this->assertEquals('Author 3', $blogs[1]->author->name);
		$this->assertEquals(3, $blogs[1]->author->id);
	}


	public function testStoreRelatedObjectMany() {
        $bStorage = new \ArrayObject();
        $aStorage = new \ArrayObject();
		$this->populateBlogsAuthors($bStorage, $aStorage);

        $blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');
		$authors->addRelation('blogs', new \Maphper\Relation\Many($blogs, 'id', 'authorId'));

		$author = $authors[2];

		$count = count($author->blogs);

		$blog = new stdclass;
		$blog->title = 'Added blog';

		$author->blogs[] = $blog;

		$this->assertEquals($count+1, count($author->blogs));

		//check the blog has been added to the table
		$this->assertEquals(1, count($blogs->filter(['title' => 'Added blog'])));

		//Check the added blog is associated with the author
		$this->assertEquals($author->id, $blogs->filter(['title' => 'Added blog'])->item(0)->authorId);
	}


	public function testStoreRelatedObjectOneNew() {
        $bStorage = new \ArrayObject();
        $aStorage = new \ArrayObject();

        $blogs = $this->getMaphper($bStorage, 'id');
		$authors = $this->getMaphper($aStorage, 'id');

		$blogs->addRelation('author', new \Maphper\Relation\One($authors, 'authorId', 'id'));


		$blog = new stdClass;
		$blog->title = 'My First Blog';
		$blog->date = new \DateTime();
		$blog->author = new stdClass;
		$blog->author->name = 'Tom Butler';

		$blogs[] = $blog;

		$this->assertEquals(1, count($blogs));
		$this->assertEquals(1, count($authors));
	}

    public function testCountEmpty() {
        $storage = new \ArrayObject();
		$test = $this->getMaphper($storage, 'id');

		$this->assertEquals(0, count($test));
	}

    private function setUpMoviesActors($aStorage, $mStorage, $cStorage) {
		$actorNames = ['Actor 1', 'Actor 2', 'Actor 3'];
		$movieNames = ['Movie 1', 'Movie 2', 'Movie 3'];

		$actors = $this->getMaphper($aStorage, 'aid');
		foreach ($actorNames as $actorName) {
			$actor = new stdclass;
			$actor->name = $actorName;
			$actors[] = $actor;
		}

		$movies = $this->getMaphper($mStorage, 'mid');
		foreach ($movieNames as $movieName) {
			$movie = new stdclass;
			$movie->title = $movieName;
			$movies[] = $movie;
		}

		$cast = $this->getMaphper($cStorage, ['movieId', 'actorId']);

		$actors->addRelation('movies', new \Maphper\Relation\ManyMany($cast, $movies, 'mid', 'movieId'));
		$movies->addRelation('actors', new \Maphper\Relation\ManyMany($cast, $actors, 'aid', 'actorId'));


		return [$actors, $movies, $cast];
	}

	public function testManyManySave() {
		//Add some actors and movies
        $aStorage = new \ArrayObject();
        $mStorage = new \ArrayObject();
        $cStorage = new \ArrayObject();


		list($actors, $movies, $cast) = $this->setUpMoviesActors($aStorage, $mStorage, $cStorage);

		$this->assertTrue(count($actors) > 0);
		$this->assertTrue(count($movies) > 0);


		$this->assertEquals(0, count($cast));


		//Add a movie to an actor
		$actors[1]->movies[] = $movies[2];
		$this->assertEquals(1, count($cast));

		$this->assertEquals(1, count($actors[1]->movies));

		$actors[1]->movies[] = $movies[1];
		$this->assertEquals(2, count($actors[1]->movies));

		$this->assertNotEquals(0, count($cast));
	}


	public function testManyManyGet() {
        $aStorage = new \ArrayObject();
        $mStorage = new \ArrayObject();
        $cStorage = new \ArrayObject();


		list($actors, $movies, $cast) = $this->setUpMoviesActors($aStorage, $mStorage, $cStorage);


		$this->assertTrue(count($actors) > 0);
		$this->assertTrue(count($movies) > 0);


		$this->assertEquals(0, count($cast));


		//Add a movie to an actor

		$actors[1]->movies[] = $movies[1];
		$actors[1]->movies[] = $movies[2];

		$this->assertNotEquals(0, count($cast));

		$this->assertNotEquals(0, count($actors[1]->movies));


		$this->assertEquals($actors[1]->movies->item(0)->title, 'Movie 2');
		$this->assertEquals($actors[1]->movies->item(1)->title, 'Movie 3');

	}


	private function setupMoviesActorsReal($cStorage, $aStorage, $mStorage) {
		$cast = $this->getMaphper($cStorage, ['movieId', 'actorId']);
		$actors = $this->getMaphper($aStorage, 'aid');
		$movies = $this->getMaphper($mStorage, 'mid');

		$actors->addRelation('roles', new \Maphper\Relation\ManyMany($cast, $movies, 'mid', 'movieId', 'movie'));
		$movies->addRelation('cast', new \Maphper\Relation\ManyMany($cast, $actors, 'aid', 'actorId', 'actor'));

		return [$cast, $actors, $movies];
	}


	public function testManyManySaveIntermediate() {

        $aStorage = new \ArrayObject();
        $mStorage = new \ArrayObject();
        $cStorage = new \ArrayObject();
		list ($cast, $actors, $movies) = $this->setupMoviesActorsReal($cStorage, $aStorage, $mStorage);


		$actor = new \stdClass;
		//set a specific id so we can look it up later
		$actor->aid = 123;
		$actor->name = 'Samuel L. Jackson';
		//save the actor
		$actors[] = $actor;

		$movie = new \stdclass;
		$movie->mid = 8;
		$movie->title = 'Pulp Fiction';


		//save the movie
		$movies[] = $movie;

		$role = new \stdClass;
		$role->characterName = 'Jules Winnfield';
		$role->movie = $movie;


		$actor->roles[] = $role;

		$this->assertEquals(count($cast), 1);

		//Recreate mappers to clear caches
		list ($cast, $actors, $movies) = $this->setupMoviesActorsReal($cStorage, $aStorage, $mStorage);

		$actor = $actors[123];
		$this->assertEquals($actor->name, 'Samuel L. Jackson');
		$this->assertEquals(count($actor->roles), 1);

		foreach ($actor->roles as $role) {
			$this->assertEquals($role->characterName, 'Jules Winnfield');
			$this->assertEquals($role->movie->title, 'Pulp Fiction');
		}
	}


	public function testManyManySaveIntermediateMultiple() {
        $aStorage = new \ArrayObject();
        $mStorage = new \ArrayObject();
        $cStorage = new \ArrayObject();
		list ($cast, $actors, $movies) = $this->setupMoviesActorsReal($cStorage, $aStorage, $mStorage);


		$actor = new \stdClass;
		$actor->aid = 123;
		$actor->name = 'Samuel L. Jackson';
		$actor->roles = [];




		$movie = new \stdclass;
		$movie->title = 'Pulp Fiction';
		$movies[] = $movie;

		$this->assertNotNull($movie->mid);

		//Create a role
		$role = new \stdClass;
		//Set the character name for the role
		$role->characterName = 'Jules Winnfield';
		//Assign the movie to the role
		$role->movie = $movie;
		//Assign the role to the actor
		$actor->roles[] = $role;



		$movie = new \stdclass;
		$movie->title = 'Snakes on a Plane';
		$movies[] = $movie;


		//Create a role
		$role = new \stdClass;
		//Set the character name for the role
		$role->characterName = 'Neville Flynn';
		//Assign the movie to the role
		$role->movie = $movie;
		//Assign the role to the actor
		$actor->roles[] = $role;


		//save the actor
		$actors[] = $actor;


		unset($actor);
		list ($cast, $actors, $movies) = $this->setupMoviesActorsReal($cStorage, $aStorage, $mStorage);

		$actor = $actors[123];

		$this->assertEquals($actor->name, 'Samuel L. Jackson');

    $iterator = $actor->roles->getIterator();
		$this->assertEquals(2, count($actor->roles));
		$role = $iterator->current();
		$this->assertEquals('Jules Winnfield', $role->characterName);
		$this->assertEquals('Pulp Fiction', $role->movie->title);

		$iterator->next();
		$role = $iterator->current();
		$this->assertEquals('Neville Flynn', $role->characterName);
		$this->assertEquals('Snakes on a Plane', $role->movie->title);
	}

	public function testSaveUpdate() {
        $storage = new \ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');
		//$this->assertTrue(count($mapper) == 0);
		$date = new DateTime();
		$blog = new stdclass;
		$blog->id = 12;
		$blog->title = 'test1';
		$blog->date = $date;

		$blog2 = new stdclass;
		$blog2->id = 12;
		$blog2->title = 'test2';
		$blog2->date = $date;

		$mapper[] = $blog;

		// Update entry

		$mapper[] = (object) ['id' => 12, 'title' => 'test2'];

		$this->assertEquals($blog2, $mapper[12]);
	}

    public function testNotFilter() {
        $storage = new \ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');
        $value = (object)['name' => 'test1', 'type' => 'include'];
        $mapper[] = $value;
        $mapper[] = (object)['name' => 'test1', 'type' => 'not_include'];

        $filtered = $mapper->filter([
            Maphper::FIND_NOT => [
                'type' => 'not_include'
            ]
        ]);
        $this->assertEquals($value, $filtered->getIterator()->current());
    }

    public function testLessFilter() {
        $storage = new \ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');
        $filtered = $mapper->filter([
            Maphper::FIND_LESS => [
                'id' => 10
            ]
        ]);
        $this->assertCount(10, $filtered);
    }

    public function testGreaterFilter() {
        $storage = new \ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');
        $filtered = $mapper->filter([
            Maphper::FIND_GREATER => [
                'id' => 15
            ]
        ]);
        $this->assertCount(4, $filtered);
    }

    public function testLessOrEqualFilter() {
        $storage = new \ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');
        $filtered = $mapper->filter([
            Maphper::FIND_LESS | Maphper::FIND_EXACT => [
                'id' => 10
            ]
        ]);
        $this->assertCount(11, $filtered);
    }

    public function testGreaterOrEqualFilter() {
        $storage = new \ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');
        $filtered = $mapper->filter([
            Maphper::FIND_GREATER | Maphper::FIND_EXACT => [
                'id' => 15
            ]
        ]);
        $this->assertCount(5, $filtered);
    }

    public function testNoCaseFilter() {
        $storage = new \ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');
        $value = (object)['name' => 'test1', 'type' => 'include'];
        $mapper[] = $value;
        $mapper[] = (object)['name' => 'test1', 'type' => 'INCLUDE'];

        $filtered = $mapper->filter([
            Maphper::FIND_NOCASE => [
                'type' => 'include'
            ]
        ]);
        $this->assertCount(2, $filtered);
    }

    public function testBetweenFilter() {
        $storage = new \ArrayObject();
        $this->populateBlogs($storage);
		$mapper = $this->getMaphper($storage, 'id');
        $filtered = $mapper->filter([
            Maphper::FIND_BETWEEN => [
                'id' => [11,18]
            ]
        ]);
        $this->assertCount(8, $filtered);
    }

    public function testOrFilter() {
        $storage = new \ArrayObject();
		$mapper = $this->getMaphper($storage, 'id');
        $value1 = (object) ['name' => 'test1', 'type' => 'include'];
        $value2 = (object) ['name' => 'test3', 'type' => 'not_include'];
        $mapper[] = $value1;
        $mapper[] = (object) ['name' => 'test2', 'type' => 'other'];
        $mapper[] = $value2;

        $filtered = $mapper->filter([
            Maphper::FIND_OR => [
                'type' => 'include',
                'name' => 'test3'
            ]
        ]);
        $iterator = $filtered->getIterator();
        $this->assertEquals($value1, $iterator->current());
        $iterator->next();
        $this->assertEquals($value2, $iterator->current());
    }
}
