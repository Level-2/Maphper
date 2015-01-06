<?php 
/*
 * Integration tests. Tests Maphper working with a Database DataSource
 */
class DatabaseTest extends PHPUnit_Framework_TestCase {

	private $maphper;
	private $pdo;
	
	public function __construct() {
		parent::__construct();
		$this->pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'u', 'p');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		//prevent any Date errors
		date_default_timezone_set('Europe/London');
	}
	
	protected function setUp() {
		parent::setUp ();
	}

	protected function tearDown() {

	}	
	
	private function getDataSource($name, $primaryKey = 'id', array $options = []) {
		return new \Maphper\DataSource\Database($this->pdo, $name, $primaryKey, $options);		
	}
	
	
	private function tableExists($name) {
		$result = $this->pdo->query('SHOW TABLES LIKE "' . $name . '"');
		return $result->rowCount() == 1;
	}
	
	private function dropTable($name) {
		$this->pdo->query('DROP TABLE IF EXISTS ' . $name);
	}
	
	public function testCreateTable() {
		
		if ($this->tableExists('foo')) {
			$this->dropTable('foo');
		}		
		
		$this->assertFalse($this->tableExists('foo'));
		
		$mapper = new \Maphper\Maphper($this->getDataSource('foo', 'id', ['editmode' => true]));
		$blog = new stdclass;
		$blog->title = 'Foo';
		$mapper[] = $blog;
		
		$this->assertTrue($this->tableExists('foo'));		
	}
	
	
	public function testSave() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		//$this->assertTrue(count($mapper) == 0);
		$blog = new stdclass;
		$blog->id = 12;
		$blog->title = md5(uniqid('foo'));

		$mapper[] = $blog;
	
		//Check it's been saved in the mapper
		$this->assertTrue(count($mapper) == 1);
		
		//Of course that doesn't mean it's also been stored in the database correctly...
		$result = $this->pdo->query('SELECT * FROM blog WHERE id = 12')->fetch();
		
		$this->assertEquals($result['title'], $blog->title);		
		
	}
	
	
	public function testSaveNull() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
	
		$blog = new stdclass;
		$blog->id = 4;
		$blog->title = null;
		$mapper[] = $blog;
	
		$result = $this->pdo->query('SELECT * FROM blog WHERE id = 4')->fetch();
	
		$this->assertEquals(null, $result['title']);
	
	}
	
	
	public function testSetToNull() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
	
		$blog = new stdclass;
		$blog->id = 4;
		$blog->title = 'A title';
		$mapper[] = $blog;
	
	
		$result = $this->pdo->query('SELECT * FROM blog WHERE id = 4')->fetch();
	
		//Check the title was set originally correctly
		$this->assertEquals($result['title'], $blog->title);
	
		//unset $mapper to clear any caching
	
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
	
		$blog->title = null;
		$mapper[] = $blog;
	
	
		$result = $this->pdo->query('SELECT * FROM blog WHERE id = 4')->fetch();
	
		//Now see if it's been correctly updated to NULL
		$this->assertEquals(null, $result['title']);
	}
	
	
	public function testNotExists() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog'));
		$this->assertFalse(isset($mapper[99]));
	}
	

	private function populateBlogs() {
		$this->dropTable('blog');
		
		
		
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		for ($i = 1; $i <= 20; $i++) {
			$blog = new stdClass;
			$blog->title = 'blog number ' . $i;
			$blog->rating = 5;
			
			$blog->date = new \DateTime('2015-01-0' . ($i % 5 + 1));
			$mapper[] = $blog; 
		}
		$this->assertEquals(20, count($mapper));
	}
	

	public function testFindById() {
		$this->populateBlogs();
		$mapper = new \Maphper\Maphper($this->getDataSource('blog'));
		
		$blog = $mapper[8];
		$this->assertEquals($blog->title, 'blog number 8');
		
	}
	
	public function testPkWrite() {
		$blog = new Blog;
		$blog->title = 'Test Write By PK';
		
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		
		$mapper[7] = $blog;
		
		$result = $this->pdo->query('SELECT * FROM blog WHERE id = 7')->fetch();
		
		$this->assertEquals($result['title'], $blog->title);
	}
	
	public function testLoop() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		
		$iterations = 0;
		foreach ($blogs as $blog) {
			$iterations++;
			$this->assertEquals($blog->title, 'blog number ' . $blog->id);
		}
		
		$this->assertEquals(20, $iterations);
	}
	
	public function testSort() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
	
		$blogs = $blogs->sort('id desc');
		$blogs->rewind();
		$blog = $blogs->current();
		$this->assertEquals($blog->title, 'blog number 20');
	}
	
	public function testLimit() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		
		$blogs = $blogs->limit('5');
		$iterations = 0;
		foreach ($blogs as $blog) {
			$iterations++;
		}
		
		$this->assertEquals(5, $iterations);
	}
	
	public function testOffset() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		
		$blogs = $blogs->offset(5);
		
		$blogs->rewind();
		$this->assertEquals(6, $blogs->current()->id);
		
	}
	
	public function testInvalidFind() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$blogs = $blogs->filter(['unknownColumn' => 20]);
		$this->assertEquals(count($blogs), 0);
	}
	
	public function testCreateNew() {
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$blog = $blogs[null];
		$this->assertTrue(is_object($blog));
	}
	
	public function testResultClassNew() {
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'blog']));
		$blog = $blogs[null];
		$this->assertInstanceOf('blog', $blog);
	}
	
	public function testResultClassExisting() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'blog']));
		
		foreach ($blogs as $blog) {
			$this->assertInstanceOf('blog', $blog);
		}
	}
	
	/*
	 * Checks to see if maphper can successfuly write to private properties (It should!)
	 */
	public function testResultClassPrivateProperties() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'BlogPrivate']));
	
		$blog = $blogs[2];
		$this->assertInstanceOf('BlogPrivate', $blog);
		$this->assertEquals('blog number 2', $blog->getTitle());
	}
	
	public function testResultClassPrivatePropertiesWrite() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'BlogPrivate']));
	
		$blog = $blogs[2];
		
		$blog->setTitle('Title Updated');
		
		$blogs[] = $blog;
		
		//Reload the mapper to ensure no cacheing is used
		unset($blogs);
		unset($blog);
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'BlogPrivate']));
		
		$blog = $blogs[2];
		
		$this->assertEquals('Title Updated', $blog->getTitle());
	}
	
	
	public function testLoopKey() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'blog']));
		
		foreach ($blogs as $id => $blog) {
			$this->assertEquals($id, $blog->id);
		}
	}
	
	public function testAlterColumnType() {
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['resultClass' => 'blog', 'editmode' => true]));
		$blog = $blogs[3];
		$blog->rating = 'VVV';
		
		$blogs[] = $blog;
		
	}
	
	public function testMultiPk() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2'], ['editmode' => true]));
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
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2', 'id3', 'id4'], ['editmode' => true]));
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
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2'], ['editmode' => true]));
		
		$blog = new Blog;
		$blog->title = 'ABC';
		
		$mapper[13][15] = $blog;
		
		$result = $this->pdo->query('SELECT * FROM blog WHERE id1 = 13 AND id2 = 15')->fetch();
		
		$this->assertEquals($result['title'], $blog->title);
	}
	
	public function testMultiPkWrite2() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2', 'id3', 'id4'], ['editmode' => true]));
	
		$blog = new Blog;
		$blog->title = 'ABC123';
		
	
		$mapper[13][15][3][4] = $blog;
	
		$result = $this->pdo->query('SELECT * FROM blog WHERE id1 = 13 AND id2 = 15 AND id3 = 3 and id4 = 4')->fetch();
	
		$this->assertEquals($result['title'], $blog->title);
	}
	
	public function testMultiPkOffsetExists() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2', 'id3', 'id4'], ['editmode' => true]));
		
		$blog = new Blog;
		$blog->title = 'ABC123';
		
		
		$mapper[13][15][3][4] = $blog;
		
		$this->assertTrue(isset($mapper[13][15][3][4]));
		
		//And check that it returns false when there is no record under that PK
		$this->assertFalse(isset($mapper[13][15][3][2]));
		$this->assertFalse(isset($mapper[12][15][3][4]));
	}
	
	
	public function testMultiPkUnset() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', ['id1', 'id2', 'id3', 'id4'], ['editmode' => true]));
	
		$blog = new Blog;
		$blog->title = 'ABC123';
	
	
		$mapper[13][15][3][4] = $blog;
	
		$result = $this->pdo->query('SELECT * FROM blog WHERE id1 = 13 AND id2 = 15 AND id3 = 3 and id4 = 4')->fetch();
	
		$this->assertEquals($result['title'], $blog->title);
		
		
		unset($mapper[13][15][3][4]);
		
		$result = $this->pdo->query('SELECT count(*) as count FROM blog WHERE id1 = 13 AND id2 = 15 AND id3 = 3 and id4 = 4')->fetch();
		
		$this->assertEquals(0, $result['count']);
		
	}
	
	
	public function testDateColumnCreate() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		
		$blog = new Blog;
		$blog->title = 'ABC123';
		$blog->date = new \DateTime;
		
		$mapper[] = $blog;
		
		$result = $this->pdo->query('SHOW COLUMNS FROM blog WHERE field = "date"')->fetch();
		$this->assertEquals('datetime', strtolower($result['Type']));		
	}
	
	
	public function testDateColumnSave() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
	
		$blog = new Blog;
		$blog->title = 'ABC123';
		$blog->date = new \DateTime;
		$blog->id = 2;
		$mapper[] = $blog;
	
		$result = $this->pdo->query('SELECT `date` FROM blog WHERE id = 2')->fetch();
		$this->assertEquals($result['date'], $blog->date->format('Y-m-d H:i:s'));
	}
	
	public function testDateColumnRead() {
		$this->dropTable('blog');
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		
		$blog = new Blog;
		$blog->title = 'ABC123';
		$blog->date = new \DateTime;
		$blog->id = 7;
		$mapper[] = $blog;
		
		//Create a new mapper instance to avoid any caching
		$mapper = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		
		$blog7 = $mapper[7];
		
		$this->assertInstanceOf('\DateTime', $blog7->date);		
		$this->assertEquals($blog->date->format('Y-m-d'), $blog7->date->format('Y-m-d'));
	
	}
	
	
	public function testDateColumnFilter() {
		$this->dropTable('blog');
		$this->populateBlogs();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		
		$b = $blogs->filter(['date' => new \DateTime('2015-01-02')]);
		
		$this->assertEquals(count($b), 4);
		
		
	}
	
	
	private function populateBlogsAuthors() {
		$this->dropTable('blog');
		$this->dropTable('author');
		
		
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		$authors = new \Maphper\Maphper($this->getDataSource('author', 'id', ['editmode' => true]));
		
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
		
		$this->populateBlogsAuthors();

		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$authors = new \Maphper\Maphper($this->getDataSource('author'));

		$relation = new \Maphper\Relation(\Maphper\Relation::ONE, $authors, 'authorId', 'id');
		$blogs->addRelation('author', $relation);
		
		
		$blog2 = $blogs[2];

		$this->assertNotEquals($blog2->author, null);		
		$this->assertEquals($blog2->authorId, $blog2->author->id);
		$this->assertEquals('Author 2', $blog2->author->name);
	}
	
	
	public function testFetchRelationMany() {
		$this->populateBlogsAuthors();
		
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$authors = new \Maphper\Maphper($this->getDataSource('author'));
		
		$authors->addRelation('blogs', new \Maphper\Relation(\Maphper\Relation::MANY, $blogs, 'id', 'authorId'));
		$author2 = $authors[2];
		
		//There were 20 blogs spread equally between 2 authors so this author should have 10 blogs
		$this->assertEquals(count($author2->blogs), 10);	
		$this->assertNotEquals(null, $author2->blogs->item(0)->title);		
	}
	
	
	public function testStoreRelatedObjectOne() {
		$this->populateBlogsAuthors();
		
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$authors = new \Maphper\Maphper($this->getDataSource('author'));		
		$blogs->addRelation('author', new \Maphper\Relation(\Maphper\Relation::ONE, $authors, 'authorId', 'id'));
		
		
		$blog2 = $blogs[2];
		
		$this->assertEquals('Author 2', $blog2->author->name);
		
		$author3 = new stdclass;
		$author3->id = 3;
		$author3->name = 'Author 3';
		
		$blog2->author = $author3;
		
		//Save the blog with the new author
		$blogs[] = $blog2;
		
		
		//Get a new instance of the mapper to avoid any caching
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$blogs->addRelation('author', new \Maphper\Relation(\Maphper\Relation::ONE, $authors, 'authorId', 'id'));
		
		
		$this->assertEquals('Author 3', $blogs[2]->author->name);
		$this->assertEquals(3, $blogs[2]->author->id);		
	}
	
	
	public function testStoreRelatedObjectMany() {
		$this->populateBlogsAuthors();
		$blogs = new \Maphper\Maphper($this->getDataSource('blog'));
		$authors = new \Maphper\Maphper($this->getDataSource('author'));
		$authors->addRelation('blogs', new \Maphper\Relation(\Maphper\Relation::MANY, $blogs, 'id', 'authorId'));
		
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
		$this->dropTable('blog');
		$this->dropTable('author');
		
		$blogs = new \Maphper\Maphper($this->getDataSource('blog', 'id', ['editmode' => true]));
		$authors = new \Maphper\Maphper($this->getDataSource('author', 'id', ['editmode' => true]));
		
		$blogs->addRelation('author', new \Maphper\Relation(\Maphper\Relation::ONE, $authors, 'authorId', 'id'));
		
		
		$blog = new stdClass;
		$blog->title = 'My First Blog';
		$blog->date = new \DateTime();
		$blog->author = new stdClass;
		$blog->author->name = 'Tom Butler';
		
		$blogs[] = $blog;
		
		$this->assertEquals(1, count($blogs));
		$this->assertEquals(1, count($authors));
	}


}

class Blog {
	
	
}

class BlogPrivate {
	private $title;
	
	public function getTitle() {
		return $this->title;
	}
	
	public function setTitle($title) {
		$this->title = $title;
	}
}

