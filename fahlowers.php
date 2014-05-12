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
		$this->auth();
		$this->me = $this->getSelf();
		if(isset($_GET['action'])) {
			$this->action = $_GET['action'];
		}
	}

	function auth() {
		session_start();

	   if (! isset($_SESSION['oauth_token'])) {
		  // get the request token
		  $reply = $this->cb->oauth_requestToken(array(
			 'oauth_callback' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
		  ));

		  // store the token
		  $this->cb->setToken($reply->oauth_token, $reply->oauth_token_secret);
		  $_SESSION['oauth_token'] = $reply->oauth_token;
		  $_SESSION['oauth_token_secret'] = $reply->oauth_token_secret;
		  $_SESSION['oauth_verify'] = true;

		  // redirect to auth website
		  $auth_url = $this->cb->oauth_authorize();
		  header('Location: ' . $auth_url);
		  die();

	   } elseif (isset($_GET['oauth_verifier']) && isset($_SESSION['oauth_verify'])) {
		  // verify the token
		  $this->cb->setToken($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
		  unset($_SESSION['oauth_verify']);

		  // get the access token
		  $reply = $this->cb->oauth_accessToken(array(
			 'oauth_verifier' => $_GET['oauth_verifier']
		  ));

		  // store the token (which is different from the request token!)
		  $_SESSION['oauth_token'] = $reply->oauth_token;
		  $_SESSION['oauth_token_secret'] = $reply->oauth_token_secret;

		  // send to same URL, without oauth GET parameters
		  header('Location: ' . basename(__FILE__));
		  die();
	   } else {
		   $this->cb->setToken($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
	   }

	}

	function getSelf() {
		$reply = $this->cb->account_verifyCredentials();
		return $reply;
	}

	function getFollowers() {
		$time = $this->getCurrentTimestamp();
		// Do the first page of followers
		$reply = $this->getFollowerList();
		if(isset($reply->error)) {
			$this->handleException($reply->error, $reply->httpstatus);
		}
		foreach($reply->users as $user) {
			$this->stashFollowerObj($user, $time);
		}

		// Grab the cursor
		$nextCursor = $reply->next_cursor_str;

		// Run through all pages of data while there is a cursor
		while($nextCursor > 0) {
			$reply = $this->getFollowerList($nextCursor);

			foreach($reply->users as $user) {
				$this->stashFollowerObj($user, $time);
			}

			$nextCursor = $reply->next_cursor_str;
		}

		$this->printFollowers($time);
	}

	function printFollowers($time) {
		print "Current server time: ".$this->getFormattedTime($time)." (".$this->getCurrentTimestamp().")<br>";
		print "<ol>";
		foreach($this->followers as $fObj) {
			print "<li>".$fObj->name." (<a href='https://twitter.com/".$fObj->screen_name."'>".$fObj->screen_name."</a>)</li>";
		}
		print "</ol>";
	}

	function stashFollowerObj($fObj, $time) {
		// This will eventually be some hippy database shit
		$this->followers[] = $fObj;
		$sql = "INSERT INTO scans VALUES (".$this->me->id.",".$time.",".$fObj->id.",'".sqlite_escape_string ($fObj->name)."','".sqlite_escape_string ($fObj->screen_name)."',".$time.")";
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
		print ".body { margin-top: 1em; margin-bottom: 1em; width: 50%;}";
		print "</style>";
		print "</head>";
		print "<body>";
		print "<div class='header'>";
		print "<h1>Fahlowers</h1>";
		print "Twitter follower analysis... without the ads!<br>";
		print "This application thinks that you are: ".$this->me->name." (<a href='https://twitter.com/".$this->me->screen_name."'>".$this->me->screen_name."</a>)<br>";
		print "<ul class='nav'>";
		print "<li><a href='fahlowers.php'>Home</a> (and help)</li>";
		print "<li><a href='?action=current'>Current Followers</a> (Only click once or twice per 15 minutes)</li>";
		print "<li><a href='?action=scans'>Follower History</a></li>";
		print "<li><a href='?action=changes'>Changes</a></li>";
		print "</div>";
		print "<div class='body'>";
		if(!$this->me->name) {
			#$this->handleException("The app has forgotten who you are. Please close this window and open a new instance of the app.","NONE");
		}
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
		print "Last Update Timestamp: ".$this->getFormattedTime($results[0]['timestamp']);
		print "<ol>";
		foreach($results as $row) {
			print "<li>".$row['follower_name']." (<a href='http://twitter.com/".$row['follower_handle']."'>".$row['follower_handle']."</a>)</li>";
		}
		print "</ol>";
	}

	function getScans() {
		$sql = "SELECT DISTINCT timestamp FROM scans WHERE user_id=".$this->me->id." ORDER BY timestamp DESC";
		$results = getDbResults($sql);
			if(!$results) {
				print "Hey! It seems you don't have any data here. Click on Current Followers above every once in a while to collect some data points.";
			}
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
		print "Follower Updates (most recent first). Click to compare results.<br>";
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
		print "<ol>";
		foreach($recent_data as $recent_follower) {
			if(isset($compare_data[$recent_follower['id']])) {
				// We dont care
				//print "Follower is not new (exists in both) - ".$recent_follower['id']."<br>";
			} else {
				print "<li>".$recent_follower['name']." (<a href='https://twitter.com/".$recent_follower['sn']."'>".$recent_follower['sn']."</a>) has started following you</li>";
			}
		}
		print "</ol>";

		print "<ol>";
		foreach($compare_data as $old_follower) {
			if(isset($recent_data[$old_follower['id']])) {
				// We dont care
				//print "Follower is not new (exists in both) - ".$old_follower['id']."<br>";
			} else {
				print "<li>".$old_follower['name']." (<a href='https://twitter.com/".$old_follower['sn']."'>".$old_follower['sn']."</a>) has stopped following you</li>";
			}
		}
		print "</ol>";
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
				print "<p>Welcome to Fahlowers! Here you can see statistics on who has been following you on Twitter. <h2>First Time</h2>If this is your first time on this app, you won't see very much other than your current followers (available via the Current Followers link above). Beware that the Twitter API limits the number of times you can use this per 15 minutes. Clicking on it will show you a list of your current followers and stash this list for the next time you come back. You can see all stashed lists in the Follower History are. <h2>Seeing Changes</h2>After a few days have gone by and you've saved a few lists (done by simply navigating to the Current Followers page) you can click Changes above and compare two different lists. You will be shown the difference between them (who has started and who has stopped following you).<h2>Privacy</h2>I cannot see anything in your account beyond it's public profile. I don't store anything more than a bunch of Twitter ID Numbers and timestamps. This project is 100% open source so you can see exactly what code is running (see Github link below).<h2>Why this exists</h2>I was tired of seeing advertisement tweets from fllwrs.com in my feed, so I wrote this to do the same thing without ads. Also because I wanted to write a Twitter app. And because I had nothing better to do. Got feature requests or feedback? Shoot me an email! (grant@grantcohoe.com)";
		}
		$this->printFooter();
	}
}

$f = new fahlower();
$f->main();
