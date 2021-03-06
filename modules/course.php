<?php
include_once __DIR__ . '/generic_object.php';
include_once __DIR__ . '/category.php';
include_once __DIR__ . '/db/DB.php';
class Course extends GenericObject {
	// ERROR CODES
	const EXISTS = 1000;
	const NOT_EXISTS = 1001;
	const NO_DATA = 1002;
	
	// Languages
	const UK = 1;
	const RU = 2;
	const EN = 3;
	
	// Formats
	const ONLINE = 1;
	const WEBCAST = 2;
	const OFFLINE = 3;
	public $languages = array (
			1 => 'Українська',
			2 => 'Русский',
			3 => 'English' 
	);
	public $formats = array (
			1 => 'Online',
			2 => 'Webcast',
			3 => 'Offline' 
	);
	protected $tablename = 'courses';
	protected $checker = array (
			'active' => 'activeCheck',
			'category_id' => 'categoryCheck', 
			'search' => 'search'
	);
	function add($data) {
		if (! isset ( $data ['name'] ) || ! isset ( $data ['category_id'] ) || ! isset ( $data ['format_id'] ) || ! isset ( $data ['language'] ) || ! isset ( $data ['describe'] )) {
			throw new Exception ( "No data", self::NO_DATA );
		}
		
		$db = new DB ();
		$db->select ( "SELECT * FROM {$this->tablename} WHERE name=$1 AND category_id=$2 AND language=$3", array (
				$data ['name'],
				$data ['category_id'],
				$data ['language'] 
		) );
		if ($db->valid ())
			throw new Exception ( "Course exists", self::EXISTS );
		$data ['created'] = date ( 'Y-m-d H:i:s', time () );
		$data ['author_id'] = $_SESSION ['user_id'];
		$data ['category_name'] = $this->getCategoryName($data ['category_id']);
		return parent::add ( $data );
	}
	function invert($id) {
		$db = new DB ();
		$db->select ( "SELECT * FROM {$this->tablename} WHERE id=$1", array (
				$id 
		) );
		if ($db->valid ()) {
			$data = $db->current ();
			$data ['active'] = $data ['active'] == 't' ? 'f' : 't';
			$this->update ( $id, array (
					'active' => $data ['active'] 
			) );
		}
	}
	function get($cond) {
		try {
			return parent::get ( $cond );
		} catch ( Exception $e ) {
			throw new Exception ( "Course is not exists", self::NOT_EXISTS );
		}
	}
	function upload($id) {
		if ($_FILES ['file'] ['size'] == 0)
			return;
		switch ($_FILES ['file'] ['type']) {
			case 'application/x-zip-compressed' :
			case 'application/zip' :
				$zip = new ZipArchive ();
				$zip->open ( $_FILES ['file'] ['tmp_name'] );
				$dir = __DIR__ . '/../../files/courses/' . $id;
				if (! file_exists ( $dir ))
					mkdir ( $dir );
				$zip->extractTo ( $dir );
				break;
			default :
				throw new Exception ( "Not zip or rar archive" );
		}
	}
	function download($id) {
		$dir = __DIR__ . '/../../files/courses/' . $id . '/';
		if (file_exists ( $dir )) {
			$data = $this->get ( array (
					'id' => $id 
			) );
			$i = 0;
			$data ['name'] = preg_replace ( '/\/|\\|\?|\%|\*|\:\||\"|\<|\>|\+/', '', $data ['name'] );
			$filename = '/var/tmp/' . $data ['name'];
			if (file_exists ( $filename . '.zip' ))
				while ( file_exists ( $filename . "($i).zip" ) ) {
					$i ++;
					if ($i > 10)
						break;
				}
			if ($i > 0)
				$filename .= "($i).zip";
			else
				$filename .= '.zip';
			Zip::packDir ( $dir, $filename );
			
			if (file_exists ( $filename )) {
				header ( 'Content-Description: File Transfer' );
				header ( 'Content-Type: application/zip' );
				header ( 'Content-Disposition: attachment; filename="' . basename ( $filename ) . '"' );
				header ( 'Expires: 0' );
				header ( 'Cache-Control: must-revalidate' );
				header ( 'Pragma: public' );
				header ( 'Content-Length: ' . filesize ( $filename ) );
				readfile ( $filename );
				unlink ( $filename );
			}
			return true;
		}
	}
	function search($val) {
			return   " (courses.name ILIKE '%" . pg_escape_string ( $val ) . "%' ".
					"OR courses.describe ILIKE '%" . pg_escape_string ( $val ) . "%' ".
					"OR courses.category_name ILIKE '%" . pg_escape_string ( $val ) . "%') ";
		}
	function activeCheck($val) {
			return 'active = ' . ($val ? 'true' : 'false');
		}
	function categoryCheck($val) {
		return 'category_id = ' . intval ( $val );
	}
	function getCategoryName($category_id=''){
		$category = new Category ();
		$enum = $category->enumerate ( null );
		if ($enum) {
			foreach ( $enum as $key => $val ) {
				if ($val ['id'] != 0)
					$items [$val ['id']] = $val;
			}
			$categories = $this->makeCategoryList ( $items, $category_id );
		}
		return $categories;
	}
	function makeCategoryList(&$items, $category_id) {
		$category = $items [$category_id] ['name'];
		if ($items [$category_id] ['parent_id'] != 0)
			$category = $this->makeCategoryList ( &$items, $items [$category_id] ['parent_id'] ) . " > " . $category;
		return $category;
	}
	function getCategoriesForSelect($category_id){
		$category = new Category ();
		$enum = $category->enumerate ( null );
		if ($enum) {
			foreach ( $enum as $key => $val ) {
				if ($val ['id'] != 0)
					$items [$val ['parent_id']] [] = $val;
			}
			foreach ( $items as $key => $val ) {
				asort ( $items [$key] );
			}
			asort ( $items );
			$categories = '<option></option>' . Course::makeFilterCategoryList ( $items, 0, $category_id );
		}
		return $categories;
	}
	function makeFilterCategoryList(&$items, $id, $category_id, $space = '') {
		foreach ( $items [$id] as $key => $val ) {
			$categories .= "<option value=\"$val[id]\"" . ($val ['id'] == $category_id ? ' selected' : '') . ">" . $space . htmlspecialchars ( $val ['name'], ENT_QUOTES ) . "</option>" . NL;
			if (isset ( $items [$val ['id']] ))
				$categories .= Course::makeFilterCategoryList ( $items, $val ['id'], $category_id, $space . '&nbsp;&nbsp;&nbsp;' );
		}
		return $categories;
	}	
}
