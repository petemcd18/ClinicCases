<?php
//script to add, update and delete case notes
session_start();
require('../auth/session_check.php');
require('../../../db.php');
require('../utilities/convert_times.php');
require('../utilities/convert_case_time.php');

//Get variables
if (isset($_POST['csenote_casenote_id']))
{$case_note_id = $_POST['csenote_casenote_id'];}

if (isset($_POST['csenote_case_id']))
{$case_id = $_POST['csenote_case_id'];}

if (isset($_POST['csenote_date']))
{
	$selected_date = $_POST['csenote_date'];
	$date = date_to_sql_datetime($selected_date);
}

if (isset($_POST['csenote_description']))
{$description = $_POST['csenote_description'];}

if (isset($_POST['csenote_hours']))
{$hours  = $_POST['csenote_hours'];}

if (isset($_POST['csenote_minutes']))
{
	$minutes = $_POST['csenote_minutes'];
	$time = convert_to_seconds($hours,$minutes);
}

if (isset($_POST['csenote_user']))
{$user = $_POST['csenote_user'];}




//Generate sql and run query
switch ($_POST['query_type'])
	{	
		case "add":
		$sql = "INSERT INTO cm_case_notes  (id, case_id, date, time, description, username, datestamp) VALUES (NULL, :case_id, :date, :time, :description, :user, CURRENT_TIMESTAMP);";	
		$case_notes_process = $dbh->prepare($sql);
		$case_notes_process->execute(array(':case_id'=>$case_id,':date'=>$date,':time'=>$time,':description'=>$description,':user'=>$user));
		break;
		
		case "modify":
		$sql = "UPDATE cm_case_notes SET date = :date, time = :time, description = :description WHERE id = :case_note_id";		
		$case_notes_process = $dbh->prepare($sql);		
		$case_notes_process->execute(array(':date'=>$date,':time'=>$time,':description'=>$description,':case_note_id'=>$case_note_id));
		break;
		
		case "delete";
		$sql = "DELETE FROM cm_case_notes WHERE id = :case_note_id";		
		$case_notes_process = $dbh->prepare($sql);
		$case_notes_process->execute(array(':case_note_id'=>$case_note_id));
		break;	
	}


//Handle mysql errors
$error = $case_notes_process->errorInfo();

	if($error[1])

		{echo "Sorry, there was an error.";}

		else
		{
			
			switch($_POST['query_type']){
			case "add":
			echo "Case note added.";
			break;
			
			case "modify":
			echo "Case note modified.";
			break;
			
			case "delete":
			echo "Case note deleted.";
			break;	
				
			}		
			
		}

