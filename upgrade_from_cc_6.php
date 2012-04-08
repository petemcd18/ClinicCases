<?php
require('db.php');

echo "Beginning upgrade process</br>";

//This fixes incorrect date entries from cc6.  Date field on case notes had 00:00:00 in the
// time part which led to incorrect sorts when displaying case notes.  This was fixed in r663
// of add_time.php, but was not corrected in casenote_edit.php. There are therefore, a lot of
//incorrect entries in some dbs.
echo "Correcting date entries on case notes...<br>";

$query = $dbh->prepare("SELECT id, date, datestamp from cm_case_notes ORDER BY datestamp desc");
$query->execute();
$notes = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($notes as $note) {
	$id = $note['id'];
	$date_parts = explode(' ',$note['date']);
	$datestamp_parts = explode(' ',$note['datestamp']);

	if ($date_parts[1] = '00:00:00')
	{
		$new_date = $date_parts[0] . " " . $datestamp_parts[1];
		$update = $dbh->prepare("UPDATE cm_case_notes set date = :new_date WHERE id = :id LIMIT 1 ");
		$data = array(':new_date'=>$new_date,':id'=>$id);
		$update->execute($data);
	}

}

$error = $query->errorInfo();

if ($error[1])
	{echo $error[1];}
else
	{echo "Case note date entries corrected.<br>";}

echo "Updating db fields<br />";

$query = $dbh->prepare("ALTER TABLE  `cm_users` CHANGE  `class`  `group` VARCHAR( 20 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT  '';ALTER TABLE  `cm_logs` CHANGE  `last_ping`  `type` VARCHAR( 200 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT  '';ALTER TABLE  `cm` ADD FULLTEXT (`professor`);ALTER TABLE  `cm` ADD  `organization` VARCHAR( 250 ) NOT NULL AFTER  `last_name`");

$query->execute();

echo "Done updating db fields<br />";

echo "Adding groups table to db</br>";

$query = $dbh->prepare("CREATE TABLE IF NOT EXISTS `cm_groups` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(50) NOT NULL,
  `group_title` varchar(100) NOT NULL,
  `group_description` text NOT NULL,
  `allowed_tabs` varchar(500) NOT NULL COMMENT 'An object which controls which tabs the user is allowed to see.',
  `add_cases` int(2) NOT NULL,
  `delete_cases` int(2) NOT NULL,
  `edit_cases` int(2) NOT NULL,
  `close_cases` int(2) NOT NULL,
  `view_all_cases` int(2) NOT NULL COMMENT 'User can view all cases or only cases to which they are assigned',
  `assign_cases` int(2) NOT NULL COMMENT 'Can the user assign cases to users?',
  `add_users` int(2) NOT NULL,
  `delete_users` int(2) NOT NULL,
  `edit_users` int(2) NOT NULL,
  `activate_users` int(2) NOT NULL,
  `add_case_notes` int(2) NOT NULL,
  `edit_case_notes` int(2) NOT NULL,
  `delete_case_notes` int(2) NOT NULL,
  `documents_upload` int(2) NOT NULL,
  `documents_modify` int(2) NOT NULL,
  `add_events` int(2) NOT NULL,
  `edit_events` int(2) NOT NULL,
  `delete_events` int(2) NOT NULL,
  `add_contacts` int(2) NOT NULL DEFAULT '1',
  `edit_contacts` int(2) NOT NULL DEFAULT '1',
  `delete_contacts` int(2) NOT NULL DEFAULT '1',
  `post_in_board` int(2) NOT NULL,
  `view_board` int(2) NOT NULL,
  `edit_posts` int(2) NOT NULL,
  `change_permissions` int(2) NOT NULL,
  `supervises` int(2) NOT NULL COMMENT 'The user has other users under him who he supervises, e.g, students, associates',
  `is_supervised` int(2) NOT NULL COMMENT 'This user works on cases,but is supervised by another user',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COMMENT='Allows admin to create user groups and set definitions' AUTO_INCREMENT=5 ;");

$query->execute();


$query = $dbh->prepare("INSERT INTO `cm_groups` (`id`, `group_name`, `group_title`, `group_description`, `allowed_tabs`, `add_cases`, `delete_cases`, `edit_cases`, `close_cases`, `view_all_cases`, `assign_cases`, `add_users`, `delete_users`, `edit_users`, `activate_users`, `add_case_notes`, `edit_case_notes`, `delete_case_notes`, `documents_upload`, `documents_modify`, `add_events`, `edit_events`, `delete_events`, `post_in_board`, `view_board`, `edit_posts`, `change_permissions`, `supervises`, `is_supervised`) VALUES
(1, 'super', 'Super User', 'The super user can access all ClinicCases functions and add, edit, and delete all data.  Most importantly, only the super user can change permissions for all users.\r\nSuper User access should be restricted to a limited number of users.', '['Home',''Cases'',''Students'',''Users'',''Journals'',''Board'',''Utilities'',''Messages'']', 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0),
(2, 'admin', 'Adminstrator', 'The administrator can access all ClinicCases functions and view,edit, and delete all data.  By default, the administrator is the only user who can add new files or authorize new users.\r\n\r\nThe administrator cannot change group permissions.', '['Home','Cases','Students','Users','Board','Utilities','Messages']', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0),
(3, 'student', 'Student', 'Students can only access the cases to which they have been assigned by a professor.', '['Home','Cases','Journals','Board','Utilities','Messages']', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1),
(4, 'prof', 'Professor', 'Professors supervise students.  By default, they can assign students to cases and view, edit, and delete all data in cases to which they are assigned.', '['Home','Cases','Students','Journals','Board','Utilities','Messages']', 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0);");

$query->execute();

echo "Finished<br />";

echo "Updating cm_students";

//script to move professor field to cm_cases_students

$query = $dbh->prepare("SELECT * FROM cm");

$query->execute();

$fields = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($fields as $field) {
		$assig = explode(',',$field['professor']);

		foreach ($assig as $p)
		{
			if (!empty($p))
			{
				$date_add = $field['date_open'] . " 00:00:00";
				$insert = $dbh->prepare("INSERT INTO cm_cases_students (id,username,case_id,status,date_assigned) VALUES (NULL,'$p','$field[id]','active','$date_add')");
				$insert->execute();


			}

		}

}

echo "Done updating cm_students<br />";

echo "Updating more fields<br />";

$query = $dbh->prepare("ALTER TABLE `cm_cases_students` DROP `first_name`,DROP `last_name`;RENAME TABLE cm_cases_students TO cm_case_assignees;
");

$query->execute();

echo "Done<br />";

echo "Adding columns table<br />";

$query = $dbh->prepare("CREATE TABLE IF NOT EXISTS `cm_columns` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `db_name` varchar(50) NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `include_in_case_table` varchar(10) NOT NULL COMMENT 'Should this column be included into the data sent to the main case table?',
  `input_type` varchar(10) NOT NULL,
  `display_by_default` varchar(10) NOT NULL COMMENT 'Should this column be displayed to the case table user by default?',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COMMENT='Defines the columns to be used in ClinicCases cases table' AUTO_INCREMENT=41 ;");

$query->execute();

$query = $dbh->prepare("INSERT INTO `cm_columns` (`id`, `db_name`, `display_name`, `include_in_case_table`, `input_type`, `display_by_default`) VALUES
(2, 'id', 'Id', 'true', 'input', 'false'),
(3, 'clinic_id', 'Case Number', 'true', 'input', 'false'),
(4, 'first_name', 'First Name', 'true', 'input', 'true'),
(5, 'm_initial', 'Middle Initial', 'true', 'input', 'false'),
(6, 'last_name', 'Last Name', 'true', 'input', 'true'),
(7, 'organization', 'Organization', 'true', 'input', 'false'),
(8, 'date_open', 'Date Open', 'true', 'input', 'true'),
(9, 'date_close', 'Date Close', 'true', 'input', 'true'),
(10, 'case_type', 'Case Type', 'true', 'select', 'true'),
(11, 'professor', 'Professor', 'false', 'input', 'false'),
(12, 'address1', 'Address 1', 'false', 'input', 'false'),
(13, 'address2', 'Address 2', 'false', 'input', 'false'),
(14, 'city', 'City', 'false', 'input', 'false'),
(15, 'state', 'State', 'false', 'input', 'false'),
(16, 'zip', 'Zip', 'false', 'input', 'false'),
(17, 'phone1', 'Phone 1', 'false', 'input', 'false'),
(18, 'phone2', 'Phone 2', 'false', 'input', 'false'),
(19, 'email', 'Email', 'true', 'input', 'false'),
(20, 'ssn', 'SSN', 'true', 'input', 'false'),
(21, 'dob', 'DOB', 'true', 'input', 'false'),
(22, 'age', 'Age', 'true', 'input', 'false'),
(23, 'gender', 'Gender', 'true', 'select', 'false'),
(24, 'race', 'Race', 'true', 'select', 'false'),
(25, 'income', 'Income', 'false', 'input', 'false'),
(26, 'per', 'Per', 'false', 'input', 'false'),
(27, 'judge', 'Judge', 'false', 'input', 'false'),
(28, 'pl_or_def', 'Plaintiff/Defendant', 'false', 'input', 'false'),
(29, 'court', 'Court', 'false', 'input', 'false'),
(30, 'section', 'Section', 'false', 'input', 'false'),
(31, 'ct_case_no', 'Court Case Number', 'false', 'input', 'false'),
(32, 'case_name', 'Case Name', 'false', 'input', 'false'),
(33, 'notes', 'Notes', 'false', 'input', 'false'),
(34, 'type1', 'Type 1', 'false', 'input', 'false'),
(35, 'type2', 'Type 2', 'false', 'input', 'false'),
(36, 'dispo', 'Disposition', 'true', 'select', 'true'),
(37, 'close_code', 'Closing Code', 'false', 'input', 'false'),
(38, 'close_notes', 'Closing Notes', 'false', 'input', 'false'),
(39, 'referral', 'Referred By', 'true', 'input', 'false'),
(40, 'opened_by', 'Opened By', 'true', 'input', 'true');
");

$query->execute();
//
//Documents db has to be updated
//

echo "Updating documents db...<br />";

$query = $dbh->prepare("ALTER TABLE  `cm_documents` ADD  `containing_folder` VARCHAR( 100 ) NOT NULL AFTER  `folder`;
	ALTER TABLE  `cm_documents` ADD  `extension` VARCHAR( 10 ) NOT NULL AFTER  `url`;
	ALTER TABLE  `cm_documents` CHANGE  `url`  `local_file_name` VARCHAR( 200 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT  '';
	ALTER TABLE  `cm_documents` ADD  `text` TEXT NOT NULL AFTER  `containing_folder`;
	ALTER TABLE  `cm_documents` ADD  `write_permission` VARCHAR( 500 ) NOT NULL AFTER  `text`
");

$query->execute();


//get the document extension and put it in extension column

$query = $dbh->prepare("SELECT * from cm_documents");
$query->execute();
$documents = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($documents as $document) {

if (stristr($document['local_file_name'], 'http://') || stristr($document['local_file_name'], 'https://') || stristr($document['local_file_name'], 'ftp://'))
		{$ext = 'url';}
		else
		{$ext = strtolower(substr(strrchr($document['local_file_name'], "."), 1));}

		$id = $document['id'];

		if ($ext != '')
		{
		$update = $dbh->prepare("UPDATE cm_documents SET extension = :ext WHERE id = :id");
		$data = array(':ext'=>$ext,':id'=>$id);
		$update->execute($data);
		}
}

//rename all files in docs directory to the id + extension; update local_file_name to the new name;then move them to new CC_DOC_PATH

	// TODO

$error = $query->errorInfo();

if ($error[1])
	{echo $error[1];}
else
	{echo "Documents have been updated.<br>";}


//Update contacts db
echo "Updating contacts db...<br />";

	//Update fields
$query = $dbh->prepare("ALTER TABLE  `cm_contacts` ADD  `organization` VARCHAR( 200 ) NOT NULL AFTER  `last_name`;ALTER TABLE  `cm_contacts` ADD  `url` TEXT NOT NULL AFTER  `email`;ALTER TABLE  `cm_contacts` CHANGE  `address`  `address` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT  '';ALTER TABLE  `cm_contacts` CHANGE  `phone1`  `phone1` TEXT NOT NULL DEFAULT  '', CHANGE  `email`  `email` TEXT NOT NULL DEFAULT  '';");

$query->execute();

	//Change phone and email fields
$query = $dbh->prepare('SELECT id,phone1, phone2, fax FROM cm_contacts ORDER BY id asc');

$query->execute();

$phones = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($phones as $phone) {

	//Make a guess at what kind of phone this is

	if (stristr($phone['phone1'], 'cell')  || stristr($phone['phone1'], 'mobile')|| stristr($phone['phone1'], 'c'))
		{
			$phone1_type = 'mobile';
		}
		elseif (stristr($phone['phone1'], 'home')  || stristr($phone['phone1'], 'h'))
		{
			$phone1_type = 'home';
		}
		elseif (stristr($phone['phone1'], 'work')  || stristr($phone['phone1'], 'office') || stristr($phone['phone1'], 'w') || stristr($phone['phone1'], 'o'))
		{
			$phone1_type = 'office';
		}
		else
			{$phone1_type = 'other';}

	if (stristr($phone['phone2'], 'cell')  || stristr($phone['phone2'], 'mobile') || stristr($phone['phone2'], 'c'))
		{
			$phone2_type = 'mobile';
		}
		elseif (stristr($phone['phone2'], 'home')|| stristr($phone['phone2'], 'h'))
		{
			$phone2_type = 'home';
		}
		elseif (stristr($phone['phone2'], 'work')  || stristr($phone['phone2'], 'office')|| stristr($phone['phone2'], 'w') || stristr($phone['phone2'], 'o'))
		{
			$phone2_type = 'office';
		}
		else
			{$phone2_type = 'other ';}


	$new_phone = array($phone1_type => $phone['phone1'], $phone2_type => $phone['phone2'], 'fax' => $phone['fax']);

	$new_phone_filtered = array_filter($new_phone);//take out empty phone fields

	$json = json_encode($new_phone_filtered);

	if ($json != '[]')//empty set
	{

	$update = $dbh->prepare("UPDATE cm_contacts SET phone1 = :phone, phone2 = '', fax = '' WHERE id = :id");

	$data = array('phone'=>$json,'id'=>$phone['id']);

	$update->execute($data);

	}

}

//update email field
$query = $dbh->prepare('SELECT id,email FROM cm_contacts');

$query->execute();

$emails = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($emails as $email) {

	if ($email['email'])
	{

		$new_email = array('other' => $email['email']);

		$json = json_encode($new_email);

		//echo $json . "<br />";

		$update = $dbh->prepare("UPDATE cm_contacts SET email = :email WHERE id = :id");

		$data = array('email' => $json, 'id' => $email['id']);

		$update->execute($data);
	}

}

$query = $dbh->prepare("ALTER TABLE `cm_contacts` DROP `phone2`, DROP `fax`;");

$query->execute();

$query = $dbh->prepare("ALTER TABLE  `cm_contacts` CHANGE  `phone1`  `phone` TEXT NOT NULL DEFAULT  ''");

$query->execute();

echo "Finished updating contacts.<br />";

echo "Updating events db<br />";

$query = $dbh->prepare("ALTER TABLE `cm_events` DROP `temp_id`;ALTER TABLE  `cm_events` CHANGE  `date_due`  `start` DATETIME NOT NULL;ALTER TABLE  `cm_events` ADD  `notes` TEXT NOT NULL AFTER  `status`
");

$query->execute();

$query = $dbh->prepare("ALTER TABLE  `cm_events` ADD  `end` DATETIME NOT NULL AFTER  `start` ,
ADD  `all_day` BOOLEAN NOT NULL AFTER  `end`");

$query->execute();

echo "Done updating events db<br />";

echo "Converting old events<br />";

$query = $dbh->prepare("UPDATE cm_events SET all_day = '1'");

$query->execute();

echo "Done converting old events<br />";



echo "Upgrade successful";





