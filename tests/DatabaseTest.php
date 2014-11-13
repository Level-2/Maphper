<?php 
/*
 * Integration tests. Tests Maphper working with a Databas DataSource
 */
class DatabaseTest extends PHPUnit_Framework_TestCase {

	private $maphper;
	private $pdo;
	
	public function __construct() {
		parent::__construct();
		$this->pdo = new PDO('mysql:dbname=maphpertest;host=127.0.0.1', 'user', 'password');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
		
		echo 'saving....' . "\n\n";
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
	
}

class Blog {
	
}
