<?php

function front_page($f3)
{
	header("Location: /view/modules/201314");
}

function modules_by_year($f3)
{

	#TODO this should be dynamic based on the date
	$modules = R::find('module', "session = ? ORDER BY code", array($f3->get('PARAMS["session"]')));
	
	$modules_by_faculty = array();
	foreach($modules as $module)
	{
		if(!array_key_exists($module->facultycode, $modules_by_faculty))
		{
			$modules_by_faculty[$module->facultycode]['name'] = $module->facultyname;
			$modules_by_faculty[$module->facultycode]['modules'] = array();
		}
		array_push($modules_by_faculty[$module->facultycode]['modules'], $module);
	}

	$f3->set('title', 'Module list by course code');
	$f3->set('modules', $modules_by_faculty);
	$f3->set('userfacultycode', current_user()->facultycode);
	$f3->set('templates', array('year.htm','createmodule.htm','modulesearch.htm','modulelist.htm'));
	
	echo Template::instance()->render("main.htm");
}

function themes($f3) 
{
	#TODO this should be dynamic based on the date
	#$programs = R::find('program', "session = ?", array( "201213" ));
	$programs = R::find('program');

	$f3->set('title', 'Programs and program themes');
	$f3->set('programs', $programs);
	#$f3->set('majors', $program->sharedMajor );

	$f3->set('templates', array('programlist.htm'));
	echo Template::instance()->render("main.htm");
}

function create_module($f3) 
{
	authenticate($f3->get("PARAMS.0"));

	$input = $f3->scrub($_POST);

	$existing_module = R::findOne("module", "session = ? AND code = ?", array( $input["session"], $input["modulecode"] ) );
	
	if(isset($existing_module)){
		$f3->error( 500, "A module with this code already exists");
		return;
	}

	$new_module = R::dispense("module");
	$new_module->code = $input["modulecode"];
	$new_module->session = $input["session"];
	$new_module->title = $input["moduletitle"];
	
	
	R::store($new_module);

	header("Location: /"); 
}

function create_specification($f3) 
{
	authenticate($f3->get("PARAMS.0"));

	$input = $f3->scrub($_POST);

	$theme = R::load("major", $input["majorid"] );
	
	if(!isset($theme)){
		$f3->error( 500, "This theme does not exist.");
		return;
	}
	if(isset($theme->specification)){
		$f3->error( 500, "This specification exists already - TODO maybe redirect this to edit?");
		return;
	}

	$specification = R::dispense("specification");
	$specification->major = $theme;
	$specification_id = R::store($specification);
	$theme->specification = $specification;
	
	R::store($theme);

	header("Location: /edit/specification/$specification_id"); 
}

function create_syllabus($f3) 
{
	authenticate($f3->get("PARAMS.0"));

	$input = $f3->scrub($_POST);
	
	if(!($input["session"] > key(date_as_session())))
	{
		#TODO MUST BE UNCOMMENTED 
		$f3->error( 500, "You cannot create syllabuses for the current or past sessions");
		return;
	
	}

	$existing_module = R::findOne("module", "session = ? AND code = ?", array( $input["session"], $input["modulecode"] ) );
	
	if(!isset($existing_module))
	{
		$f3->error( 500, "This module does not exists");
		return;
	}
	if(isset($existing_module->provisionalsyllabus))
	{
		$f3->error( 500, "This syllabus exists already - TODO maybe redirect this to edit?");
		return;
	}

	$syllabus = "";
	#print_r($existing_module->ownSyllabus);
	if($existing_module->ownSyllabus)
	{
		$current_syllabus = reset($existing_module->ownSyllabus);
		$syllabus = R::dup($current_syllabus);
	}
	else
	{

		$syllabus = R::dispense("syllabus");
	}

	$syllabus->module = $existing_module;
	$syllabus->isprovisional = true;
	$syllabus->isunderreview = false;
	$syllabus->educationboardreviewed = false;
	$syllabus->cqareviewed = false;
	$syllabus->courseleaderreviewed = false;
	$syllabus->quinquenialreviewed = false;
	$syllabus->reviewedby = "";
	$syllabus->approvalnote = "";
	$syllabus->timeapproved = null;
	$syllabus_id = R::store($syllabus);
	$existing_module->provisionalsyllabus = $syllabus;
	
	R::store($existing_module);
	
	if(valid_api_key($f3->get("REQUEST.apikey")))
	{
		echo serialize( array( 'provisionalsyllabusid', $syllabus_id) );
		return;
	}
		
	header("Location: /edit/syllabus/$syllabus_id"); 
}

function view_syllabus($f3) 
{
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}
	$content = "";
	$module = $syllabus->module;
	$f3->set("title", $module->code." ".$module->title." (".$module->session.")");
	$f3->set("module", $module);
	$f3->set("syllabus", $syllabus);

	$templates = array();
	if($syllabus->isprovisional){
		$templates[] = 'provisional.htm';
	}
	$templates[] = 'syllabus.htm';

	$f3->set('templates', $templates);
	echo Template::instance()->render("main.htm");
}

function json_syllabus($f3) 
{
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}
	$module = array("module"=>$syllabus->module->export(), "syllabus"=>$syllabus->getData());
	echo json_encode($module);
}

function ecs_syllabus($f3) 
{
	$existing_module = R::findOne("module", "session = ? AND code = ?", array( $f3->get("PARAMS.session"), $f3->get("PARAMS.modulecode") ) );
	if(!isset($existing_module))
	{
		echo json_encode(array("status"=>404));
		return;
	}
	if( ! $existing_module->ownSyllabus )
	{
		echo json_encode(array("status"=>404));
		return;

	}
	$syllabus = reset($existing_module->ownSyllabus);
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}
	$f3->set("syllabus", $syllabus);
	$f3->set("module", $existing_module);
	$content = Template::instance()->render("syllabus_ecs.htm");
	$title = $existing_module->code.": ".$existing_module->title;
	$alias = "module/".$existing_module->code;
	$module = array("title"=>$title, "body"=>$content, "alias"=>$alias, "status"=>"200");
	echo json_encode($module);
}

function php_module($f3) 
{
	$existing_module = R::findOne("module", "session = ? AND code = ?", array( $f3->get("PARAMS.session"), $f3->get("PARAMS.modulecode") ) );

	if(!isset($existing_module))
	{
		echo("This module does not exists");
		exit;
	}
	if( ! $existing_module->ownSyllabus )
	{
		echo("This module has no syllabus");
		exit;

	}
	$syllabus = reset($existing_module->ownSyllabus);

	if(!isset($syllabus) || !$syllabus->id)
	{
		echo("This syllabus does not exist");
		return;
	}
	$module = array("module"=>$existing_module->export(), "syllabus"=>$syllabus->getData());
	echo serialize($module);
}

function edit_syllabus($f3) 
{
	global $API_KEYS;
	
	authenticate($f3->get("PARAMS.0"));

	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}

	if(!$syllabus->isprovisional)
	{
		$f3->error( 500, "This syllabus is not provisional");
		return;
	}

	$module = $syllabus->module;

	R::store($module);
	$f3->set('title', "Editing ".$module->code.": ".$module->title );

	$f3->set('rendered_html_content', $syllabus->renderForm());
	$f3->set('templates', array('rendered_html.htm'));
	echo Template::instance()->render("main.htm");
}

function save_syllabus($f3)
{
	authenticate($f3->get("PARAMS.0"));
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));

	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}
	$data = $syllabus->fromForm();

	R::store($syllabus);
	if($f3->get('REQUEST.passback'))
	{
		header("Location: ".$f3->get('REQUEST.passback'));
	}
	header("Location: /view/syllabus/".$syllabus->id);
}


function toreview_syllabus($f3)
{
	authenticate($f3->get("PARAMS.0"));
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}

	if(!$syllabus->canEdit())
	{
		$f3->error( 500, "You do not have permission to move this to review");
		return;
	}

	$syllabus->isunderreview = 1;

	R::store($syllabus);
	
	header( "Location: /" );
}


function review_syllabus($f3)
{
	authenticate($f3->get("PARAMS.0"));
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}
	
	if(!$syllabus->isunderreview)
	{
		$f3->error( 500, "This syllabus is not under review");
		return;
	}
	
	$user = current_user();
	if(!$syllabus->canBeReviewedBy($user))
	{
		$f3->error( 500, "You are not a reviewer for this syllabus");	
		return;
	}

	$module = $syllabus->module;
	$content = "";
	# if there is only 1 ownSyllabus that is the provisional one
	if(count($module->ownSyllabus) > 1)
	{
		$previous_syllabus = array_shift($module->ownSyllabus);
	}
	$f3->set("title", "Reviewing ".$module->code.": ".$module->title." (".$module->session.") ");
	$f3->set("module", $module);

	$templates = array();
	if(!isset($previous_syllabus)){
		$f3->set("syllabus", $syllabus);
		$content .= Template::instance()->render("syllabus.htm");
		$templates[] = 'syllabus.htm';
	}else{
		$f3->set("syllabuses", array('previous'=>$previous_syllabus, 'current'=>$syllabus));
		$templates[] = "comparesyllabuses.htm";
		
	}

	$templates[] = 'reviewtools.htm';	
	$f3->set('templates', $templates);
	echo Template::instance()->render("main.htm");
}

function approve_syllabus($f3)
{
	authenticate($f3->get("PARAMS.0"));
	$syllabus = R::load("syllabus", $f3->get('PARAMS["syllabus_id"]'));
	if(!$syllabus->id)
	{
		$f3->error( 500, "This syllabus id does not exist");
		return;
	}

	#TODO need to check that the user is a quinquenial reviewer
	if($_POST["reviewtype"] == "quinquenial")
	{
		$syllabus->quinquenialreviewed = true;
	}

	#TODO need to check that the user is a quinquenial reviewer
	if($_POST["reviewtype"] == "courseleader")
	{
		$syllabus->courseleaderreviewed = true;
	}

	if($_POST["reviewtype"] == "cqa")
	{
		$syllabus->cqareviewed = true;
	}

	if($_POST["reviewtype"] == "educationboard")
	{
		$syllabus->educationboardreviewed = true;
	}

	R::store($syllabus);
	
	# this is far too complicated for users to actually do. bottom line is if CQA says its ok then it is
	#if($syllabus->quinquenialreviewed && $syllabus->courseleaderreviewed && $syllabus->cqareviewed && $syllabus->educationboardreviewed) 
	if($syllabus->cqareviewed) 
	{
		$user = current_user();
		$syllabus->isprovisional = 0;
		$syllabus->isunderreview = 0;
		$syllabus->timeapproved = time();
		$syllabus->approvedby = $user->username; 
		$syllabus->approvalnote = $_POST["approvalnote"]; 
		$module = $syllabus->module;
		unset($module->syllabus);
		$module->ownSyllabus = array($syllabus); 
		unset($module->provisionalsyllabus);
		R::store($module);
		
	}

	R::store($syllabus);

	header( "Location: /review/dashboard" );

}

function review_dashboard($f3)
{
	authenticate($f3->get("PARAMS.0"));

	$user = current_user();

	$rg = $user->review_groups();
	if ( empty($rg))
	{
		$f3->error( 500, "You are not registered as a module reviewer");
	}

	$syllabuses = $user->syllabuses_awaiting_review();
	$f3->set('syllabuses_awaiting_review', $syllabuses);
	$f3->set('syllabuses_awaiting_review_count', count($syllabuses));

	$syllabuses = $user->syllabuses_awaiting_submission();
	$f3->set('syllabuses_awaiting_submission', $syllabuses);
	$f3->set('syllabuses_awaiting_submission_count', count($syllabuses));

	$f3->set("title", "Your Review Dashboard");
	$f3->set('templates', array('reviewdashboard.htm')); 

	echo Template::instance()->render("main.htm");
}

function login($f3)
{
}

function logout($f3)
{
	$f3->set("SESSION.authenticated", false);
	header("Location: /");
}

function report_usage($f3) 
{
	$report_start = strtotime("-1 month");	
	$report_end = time();
	if($f3->exists("REQUEST.report_start")){
		$report_start = strtotime($f3->get("REQUEST.report_start"));
	}
	if($f3->exists("REQUEST.report_end")){
		$report_end = strtotime($f3->get("REQUEST.report_end"));
	}

	$syllabuses = R::find('syllabus', " timeapproved > ? and timeapproved < ? ORDER BY timeapproved", array($report_start, $report_end));

	$f3->set("title", "Usage report");
	$f3->set("syllabuses", $syllabuses);
	$f3->set("report_start", date("Y-m-d",$report_start));
	$f3->set("report_end", date("Y-m-d",$report_end));

	$f3->set('templates', array('report_usage'));

	echo Template::instance()->render("main.htm");
}

?>