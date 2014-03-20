<?php

class Model_Module extends RedBean_SimpleModel 
{

	public $provisionalsyllabus = false;
	public $currentsyllabus = false;

	public function getProvisional() 
	{
		if(!empty($this->provisionalsyllabus)) { return $this->provisionalsyllabus; }

		if ($syllabus = $this->bean->fetchAs('syllabus')->provisionalsyllabus) 
		{
			$this->provisionalsyllabus = $syllabus;
			return $syllabus;
		}
		return false;
	}

	public function getCurrent() 
	{
		if(!empty($this->currentsyllabus)) { return $this->currentsyllabus; }

		if ($syllabus = $this->bean->fetchAs('syllabus')->currentsyllabus) 
		{
			$this->currentsyllabus = $syllabus;
			return $syllabus;
		}
		return false;
	}

	public function setProvisional($provisional) 
	{
		$this->provisionalsyllabus = $provisional;
		R::store($this);
	}

	public function setCurrent($current) 
	{
		$this->currentsyllabus = $current;
		R::store($this);
	}

	public function listFaculties()
	{
		$things = R::$f->begin()->addSQL(' SELECT DISTINCT facultycode, facultyname ')->from('module')->get();

		$faculties = array();
		foreach ($things as $pair){
			$faculties[$pair['facultycode']] = $pair['facultyname'];
		}

		asort($faculties);
		return $faculties;
	}

	public function listSessions()
	{
		$sessions = R::getCol(' SELECT DISTINCT session FROM module ORDER BY session');

		return $sessions;
	}

}

