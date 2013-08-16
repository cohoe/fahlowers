<?php

require_once('codebird-php/src/codebird.php');
require_once('db.php');
require_once('creds.php');

class fahlower {

	// Variables
	private $followers = array();
	private $cb;
	private $me;
	private $action;

	public function __construct() {
		$creds = new Creds();
		$this->cb = \Codebird\Codebird::getInstance();
		$this->cb->setConsumerKey($creds->consumerKey,$creds->consumerSecret);
		$this->cb->setToken($creds->token,$creds->secret);
		$this->me = $this->getSelf();
		$this->action = $_GET['action'];
	}

	function getSelf() {
		$handle = $this->cb->account_settings()->screen_name;
		return $this->cb->users_show("screen_name=$handle");
	}

	function getFollowers() {
		$time = $this->getCurrentTimestamp();
		print "Current server time: ".$this->getFormattedTime($time)." (".$this->getCurrentTimestamp().")<br>";
		// Do the first page of followers
		$reply = $this->getFollowerList();
		foreach($reply->users as $user) {
			$this->stashFollowerObj($user, $time);
		}

		// Grab the cursor
		$nextCursor = $reply->next_cursor_str;

		// Run through all pages of data while there is a cursor
		while($nextCursor > 0) {
			print "LOOP ";
			$reply = $this->getFollowerList($nextCursor);

			foreach($reply->users as $user) {
				$this->stashFollowerObj($user, $time);
			}

			$nextCursor = $reply->next_cursor_str;
		}
	}

	function stashFollowerObj($fObj, $time) {
		// This will eventually be some hippy database shit
		$this->followers[] = $fObj;
		print $fObj->name." - ".$fObj->screen_name."<br>";
		$sql = "INSERT INTO scans VALUES (".$this->me->id.",".$time.",".$fObj->id.",'".$fObj->name."','".$fObj->screen_name."',".$time.")";
		dbExecute($sql);
	}


	function getFollowerList($cursor=-1) {
		$reply = $this->cb->followers_list("cursor=$cursor");
		if(isset($reply->errors)) {
			$this->handleException($reply->errors[0]->message, $reply->httpstatus);
		}

		return $reply;
	}

	function exceptionHandler($e) {
		print "AW SNAP! Something went wrong.<br>";
		print "Exception: ".$e->getMessage()."<br>";
		$this->printFooter();
		die;
	}

	function handleException($msg, $http) {
		print "AW SNAP! Something went wrong.<br>";
		print "Technical error: $msg<br>";
		print "Twitter HTTP Code: $http<br>";
		$this->printFooter();
		die;
	}

	function printHeader() {
		print "<head>";
		print "<style type=text/css>";
		print ".body { margin-top: 1em; margin-bottom: 1em; }";
		print "</style>";
		print "</head>";
		print "<body>";
		print "<div class='header'>";
		print "<h1>Fahlowers</h1>";
		print "Twitter follower analysis... without the ads!";
		print "<ul class='nav'>";
		print "<li><a href='fahlowers.php'>Home</a></li>";
		print "<li><a href='?action=current'>Current Followers</a></li>";
		print "<li><a href='?action=previous'>Previous Followers</a></li>";
		print "<li><a href='?action=scans'>Follower Scan History</a></li>";
		print "<li><a href='?action=changes'>Changes</a></li>";
		print "</div>";
		print "<div class='body'>";
	}

	function printFooter() {
		print "</div>";
		print "<div class='footer'>";
		print "Written by <a href='http://grantcohoe.com'>Grant Cohoe</a><br>";
		print "Code on <a href='http://github.com/cohoe/fahlowers'>Github</a><br>";
	}

	function getCurrentTimestamp($formatted=null) {
		$date = new DateTime();

		if($formatted) {
			return $date->format('Y-m-d H:i:s');
		}

		return $date->getTimestamp();
	}

	function getFormattedTime($timestamp) {
		$date = new Datetime();
		$date->setTimestamp($timestamp);
		return $date->format('Y-m-d H:i:s');
	}

	function getAction() {
		return $this->action;
	}

	function getLastScan($id) {
		$sql = "SELECT * FROM scans WHERE user_id=$id";
		$results = getDbResults($sql);
		$this->printResults($results);
	}

	function printResults($results) {
		print "Last Scan Timestamp: ".$this->getFormattedTime($results[0]['timestamp']);
		print "<ol>";
		foreach($results as $row) {
			print "<li>".$row['follower_name']." (<a href='http://twitter.com/".$row['follower_handle']."'>".$row['follower_handle']."</a>)</li>";
		}
		print "</ol>";
	}

	function getScans() {
		$sql = "SELECT DISTINCT timestamp FROM scans ORDER BY timestamp DESC";
		$results = getDbResults($sql);
		return $results;
	}

	function printScans($scan=null) {
		if(!isset($scan)) {
			print "<ol>";
			foreach($this->getScans() as $row) {
				$url = "?action=scans&scan=".$row['timestamp'];
				print "<li><a href='$url'>".$this->getFormattedTime($row['timestamp'])."</a></li>";
			}
			print "</ol>";
		} else {
			print "Followers as of ".$this->getFormattedTime($scan).":<br>";
			$data = $this->getScan($scan);
			print "<ol>";
			foreach($data as $follower) {
				print "<li>".$follower['follower_name']." (<a href='https://twitter.com/".$follower['follower_handle']."'>".$follower['follower_handle']."</a>)"."</li>";
			}
			print "</ol>";
		}
	}

	function compareScans($recent=null) {
		print "Follower Scans (most recent first). Click to compare results.<br>";
		print "<ol>";
		foreach($this->getScans() as $row) {
			if(isset($recent)){
				$url = "?action=changes&diff=$recent&recent=".$row['timestamp'];
			} else {
				$url = "?action=changes&diff=".$row['timestamp'];
			}
			print "<li><a href='$url'>".$this->getFormattedTime($row['timestamp'])."</a></li>";
		}
		print "</ol>";
	}

	function getScan($timestamp) {
		$sql = "SELECT * FROM scans WHERE timestamp=$timestamp";
		$results = getDbResults($sql);
		return $results;
	}

	function listFollowersFromScan($scanData) {
		$followers = array();
		foreach($scanData as $row) {
			$follower['id'] = $row['follower_id'];
			$follower['name'] = $row['follower_name'];
			$follower['sn'] = $row['follower_handle'];
			$followers[$follower['id']] = $follower;
		}
		return $followers;
	}

	function printChanges($diff, $recent) {
		$scans = $this->getScans();

		if($recent < $diff) {
			$tmp = $recent;
			$recent = $diff;
			$diff = $tmp;
		}

		$recent_data = $this->getScan($recent);
		$compare_data = $this->getScan($diff);
		$recent_data = $this->listFollowersFromScan($recent_data);
		print "Comparing <a href='?action=scans&scan=$recent'>".$this->getFormattedTime($recent)."</a> (most recent) to <a href='?action=scans&scan=$diff'>".$this->getFormattedTime($diff)."</a> (other):<br>";
		$compare_data = $this->listFollowersFromScan($compare_data);
		foreach($recent_data as $recent_follower) {
			if(isset($compare_data[$recent_follower['id']])) {
				// We dont care
				//print "Follower is not new (exists in both) - ".$recent_follower['id']."<br>";
			} else {
				print $recent_follower['name']." (<a href='https://twitter.com/".$recent_follower['sn']."'>".$recent_follower['sn']."</a>) has started following you<br>";
			}
		}

		foreach($compare_data as $old_follower) {
			if(isset($recent_data[$old_follower['id']])) {
				// We dont care
				//print "Follower is not new (exists in both) - ".$old_follower['id']."<br>";
			} else {
				print $old_follower['name']." (<a href='https://twitter.com/".$old_follower['sn']."'>".$old_follower['sn']."</a>) has stopped following you<br>";
			}
		}
	}

	function getRecent($diff) {
		if(!isset($_GET['recent'])) {
			print "Pick another scan to compare to:<br>";
			$this->compareScans($recent=$diff);
		} else {
			$this->printChanges($diff, $_GET['recent']);
		}
	}

	function main() {
		$this->printHeader();
		switch($this->getAction()) {
			case 'current':
				$this->getFollowers();
				break;
			case 'scans':
				if(isset($_GET['scan'])) {
					$this->printScans($_GET['scan']);
				} else {
					$this->printScans();
				}
				break;
			case 'previous':
				$this->getLastScan(1);
				break;
			case 'changes':
				if(!isset($_GET['diff'])) {
					$this->compareScans();
				} else {
					$this->getRecent($_GET['diff']);
				}
				break;
			default:
				print "Welcome to Fahlowers!";
		}
		$this->printFooter();
	}
}

$f = new fahlower();
$f->main();