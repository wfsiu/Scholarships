<?php
	
class DataAccessLayer {
	protected $db;

	function __construct() {
		global $db_driver, $db_user, $db_pass, $db_host, $db_name;
		$dsn = $db_driver . '://' . $db_user . ':' . $db_pass . '@'
			. $db_host . '/' . $db_name;
		$this->db = &DB::Connect($dsn);
		if (PEAR::isError($this->db)) 
   			die($this->db->getMessage());
		$this->db->setFetchMode(DB_FETCHMODE_ASSOC);
		$this->userid = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : null;
	}

	function __destruct() {
		if (DB::isError($this->db))
			die('Could not connect to database!');
		$this->db->disconnect();
	}

	function select() {

	}

	function buildWhere($where) {
		$sql = '';
		for($i=0; $i < count($where); $i++){
			if ( $i == 0 ) {
				$sql = "WHERE " . $where[$i];			
			} else {
				$sql = $sql . " AND " . $where[$i];
			}
		}
		return $sql;
	}

	function gridData($params) {
		$where = array();
		$apps = isset( $params['apps'] ) ? $params['apps'] : 'unreviewed';
		$myid = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : 0;
		$items = ( isset( $params['items'] ) && is_int( $params['items'] ) ) ? $params['items'] : 50;

		$p = isset( $params['offset'] ) ? $params['offset'] : 0;
		$p = intval($p);
		$offset = " OFFSET " . ( $p * $items );		

                if ( $params['items'] == 'all' ) {
                        $limit = " ";
			$offset = " ";
                } else {
                        $limit = " LIMIT $items ";
                }
		
		if ( $params['phase'] == 1 ) {
			switch( $apps ) {
				case 'unreviewed':
					array_push( $where, ' p1count IS NULL ');
					break;
				case 'myapps':
					array_push( $where, ' mycount IS NULL ');
					break;
				default:
					break;
			}
		} else if ( $params['phase'] == 2 ) {
			switch( $apps ) {
				case 'unreviewed':
					array_push( $where, ' nscorers IS NULL ');
					break;
				case 'myapps':
					array_push( $where, ' mycount IS NULL ');
					break;
				default:
					break;
			}
		}

		if ($params['phase'] == 1) {
			$sql = "SELECT s.id, s.fname, s.lname, s.email, s.residence, s.exclude,  s.sex, (2012 - year(s.dob)) as age, (s.canpaydiff*s.wantspartial) as partial, c.country_name, coalesce(p1score,0) as p1score, p1count, mycount 
FROM scholarships s 
LEFT OUTER JOIN (select *, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id
LEFT OUTER JOIN (select scholarship_id, count(rank) as p1count from rankings where criterion = 'valid' group by scholarship_id) r3 on s.id = r3.scholarship_id 
LEFT OUTER JOIN (select scholarship_id, count(rank) as mycount from rankings where criterion = 'valid' AND user_id = $myid group by scholarship_id) r4 on s.id = r4.scholarship_id
LEFT OUTER JOIN countries c on s.residence = c.id " 
. $this->buildWhere( $where ) . "
GROUP BY s.id, s.fname, s.lname, s.email, s.residence 
HAVING p1score >= -2 and p1score <= 999 and s.exclude = 0 $limit $offset;";
		} else {
			array_push( $where, ' p1score >= 1 ' );
			$sql = "select s.id, s.fname, s.lname, s.email, s.residence, s.exclude, s.sex, (2012 - year(s.dob)) as age, (s.canpaydiff*s.wantspartial) as partial, c.country_name, coalesce(p1score,0) as p1score, coalesce(p2score,0) as p2score, coalesce(nscorers,0) as nscorers from scholarships s 
			left outer join (select scholarship_id, sum(rank) as p2score from rankings where criterion in ('onwiki','offwiki', 'future') group by scholarship_id) r on s.id = r.scholarship_id
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id
			left outer join (select scholarship_id, count(distinct user_id) as nscorers from rankings where criterion in ('onwiki','offwiki', 'future', 'program') group by scholarship_id) r3 on s.id = r3.scholarship_id			
			left outer join countries c on s.residence = c.id      
LEFT OUTER JOIN (select scholarship_id, count(rank) as mycount from rankings where criterion IN ('onwiki', 'offwiki', 'future') AND user_id = $myid group by scholarship_id) r4 on s.id = r4.scholarship_id " 
. $this->buildWhere( $where ) . "
			group by s.id, s.fname, s.lname, s.email, s.residence 
			order by s.id $limit $offset;";
		}
		$res = $this->db->getAll($sql);
		if (PEAR::isError($res)) 
    			die($res->getMessage());
    		return $res;
	}

	function myUnreviewed($myid, $phase) {
		$where = array();
		$sql = "SELECT s.id FROM scholarships s 
			LEFT OUTER JOIN (select scholarship_id, count(rank) as mycount from rankings where criterion IN ('onwiki', 'offwiki', 'future', 'program') AND user_id = $myid group by scholarship_id) r4 on s.id = r4.scholarship_id 
			WHERE mycount IS NULL;";
		$res = $this->db->getAll($sql);
		$apps = array();
		foreach ($res as $r) {
			array_push($apps, $r['id']);
		}
		return $apps;
	}

	function search($params) {
		$myid = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : 0;
		$first = isset( $params['first'] ) ? mysql_real_escape_string($params['first']) : null;
		$last = isset( $params['last'] ) ? mysql_real_escape_string($params['last']) : null;
		$citizen = isset( $params['citizen']) ? mysql_real_escape_string($params['citizen']) : null;
		$residence = isset( $params['residence']) ? mysql_real_escape_string($params['residence']) : null;
		$items = isset( $params['items'] ) ? mysql_real_escape_string($params['items']) : 50;
		$region = isset( $params['region'] ) ? mysql_real_escape_string($params['region']) : null;

                $p = isset( $params['offset'] ) ? $params['offset'] : 0;
                $p = intval($p);
                $offset = " OFFSET " . ( $p * $items );

		$limit = " LIMIT $items ";
		$where = array();
		if ( $last != null ) {
			array_push( $where, " s.lname = '" . $last . "' ");
		}
		if ( $first != null ) {
			array_push( $where, " s.fname = '" . $first . "' ");
		}
		if ( $residence != null ) {
			array_push( $where, " c.country_name = '" . $residence . "' ");
		}
		if ( $region != null ) {
			array_push( $where, " c.region = '" . $region . "' ");
		}
		$sql = "
SELECT s.id, s.fname, s.lname, s.email, s.residence, s.exclude,  s.sex, (2012 - year(s.dob)) as age, (s.canpaydiff*s.wantspartial) as partial, c.country_name, c.region, coalesce(p1score,0) as p1score, p1count, mycount 
FROM scholarships s 
LEFT OUTER JOIN (select *, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id
LEFT OUTER JOIN (select scholarship_id, count(rank) as p1count from rankings where criterion = 'valid' group by scholarship_id) r3 on s.id = r3.scholarship_id 
LEFT OUTER JOIN (select scholarship_id, count(rank) as mycount from rankings where criterion = 'valid' AND user_id = $myid group by scholarship_id) r4 on s.id = r4.scholarship_id
LEFT OUTER JOIN countries c on s.residence = c.id " . 
$this->buildWhere( $where ) . "
GROUP BY s.id, s.fname, s.lname, s.email, s.residence 
HAVING p1score >= -2 and p1score <= 999 and s.exclude = 0 $limit $offset";
		return $this->db->getAll($sql);
	}
		

	function GetScholarship($id) {
		return $this->db->query('select *, s.id, s.residence as acountry, c.country_name, r.country_name as residence_name from scholarships s 
		left outer join countries c on s.nationality = c.id 
		left outer join countries r on s.residence = r.id 
		where s.id = ?', array($id))->fetchRow();
	}

	function getNext($userid, $id, $phase) {
		$nextid = $this->getNextId($userid, $id, $phase);
		if ( $nextid != false ) {
			return $this->GetScholarship( $i );
		}
		return false;
	}

	function skipApp($userid, $id, $phase) {
		$j = 0;
                $myapps = $this->myUnreviewed($userid, $phase);
                for ( $i = $id; $i < max($myapps); $i++) {
                        if ( in_array( $i, $myapps ) ) {
				if ( $j == 1 ) {
					return $i;
				}
                                $j++;
                        }
                }
                return false;
	}

	function prevApp($userid, $id, $phase) {
                $j = 0;
                $myapps = $this->myUnreviewed($userid, $phase);
                for ( $i = $id; $i > min($myapps); $i--) {
                        if ( in_array( $i, $myapps ) ) {
                                if ( $j == 1 ) {
                                        return $i;
                                }
                                $j++;
                        }
                }
                return false;
	}

	function getNextId($userid, $id, $phase) {
		$myapps = $this->myUnreviewed($userid, $phase);
                for ( $i = $id; $i < max($myapps); $i++) {
                        if ( in_array( $i, $myapps ) ) {
         			return $i;
                        }
                }
                return false;
	}

	function GetCountAllUnrankedPhase1($id) {
		return $this->db->query("select COUNT(*), coalesce(p1self,0) as p1self, coalesce(p1score,0) as p1score from scholarships s 
				left outer join (select scholarship_id, sum(rank) as p1self from rankings where criterion = 'valid' and user_id = ? group by scholarship_id) r on s.id = r.scholarship_id		
				left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id				
				where p1self is null and s.rank >=0 and ((p1score < 3 and p1score > -3)) and s.exclude = 0;", array($id))->fetchRow();
	}		

	function GetCountAllUnrankedPhase2($id) {
		return  $this->db->query("select COUNT(*), p2self, coalesce(p1score,0) as p1score from scholarships s 
				left outer join (select scholarship_id, sum(rank) as p2self from rankings where criterion in ('offwiki', 'onwiki', 'future') and user_id = ? group by scholarship_id) r on s.id = r.scholarship_id
				left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r3 on s.id = r3.scholarship_id 
				where p2self is null and (p1score >= 3 or s.id > 1152) and s.exclude = 0;", array($id))->fetchRow();
	}
		
	function GetCountAllPhase1() {
		return $this->db->query("select COUNT(*) from scholarships s where s.exclude = 0;")->fetchRow();
	}
		
	function GetCountAllPhase2() {
		return  $this->db->query("select COUNT(*), coalesce(p1score,0) as p1score from scholarships s 
				left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r3 on s.id = r3.scholarship_id 
				where (p1score >= 3 or s.id > 1152) and s.exclude = 0;")->fetchRow();
	}

	function InsertOrUpdateRanking($user_id, $scholarship_id, $criterion, $rank) {
		if ($this->db->query("select * from rankings where user_id = ? and scholarship_id = ? and criterion = ?", array($user_id, $scholarship_id, $criterion))->numRows() > 0) {
			$sql = "update rankings set rank = $rank where user_id = $user_id and scholarship_id = $scholarship_id and criterion = '$criterion'";
			$this->db->query($sql);
		} else {
			$sql = sprintf("insert into rankings (rank, user_id, scholarship_id, criterion) values (%d, %d, %d, '%s')", $rank, $user_id, $scholarship_id, $criterion);
			$this->db->query($sql);
		}
	}

	/* depricated */
	function getRankings($id, $phase) {
		if ( $phase == 1 ) {
			$res = $this->db->getAll('select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion = "valid" and r.scholarship_id = ?', array($id));
			return $res;
		} else if ( $phase == 2 ) {
			$res = $this->db->getAll("select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion IN ('onwiki', 'future', 'offwiki', 'program') and r.scholarship_id = ? order by r.criterion, u.username, r.rank", array($id));
			return $res;
		}
		return false;
	}

        function myRankings($id, $userid, $phase) {
		if ( !isset( $this->userid ) ) {
			return false;
		}
                if ( $phase == 1 ) {
			$sql = sprintf("select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion = 'valid' and u.id = %d AND r.scholarship_id = %d", $this->userid, $id);
			$res = $this->db->getAll($sql);
                        return $res;
                } else if ( $phase == 2 ) {
                        $sql = sprintf("select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion IN ('onwiki', 'future', 'offwiki', 'program') and u.id = %d and r.scholarship_id = %d order by r.criterion, u.username, r.rank", $userid, $id);
			$res = $this->db->getAll($sql);
                        return $res;
                }
                return false;
        }
	
        function allRankings($id, $phase) {
                if ( $phase == 1 ) {
                        $res = $this->db->getAll('select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion = "valid" and r.scholarship_id = ?', array($id));
                        return $res;
                } else if ( $phase == 2 ) {
                        $sql = sprintf("select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion IN ('onwiki', 'future', 'offwiki', 'program') and r.scholarship_id = %d order by r.criterion, u.username, r.rank", $id);
			$res = $this->db->getAll($sql);
                        return $res;
                }
                return false;
        }

        function getRankingOfUser($user_id, $scholarship_id, $criterion) {
		$sql = sprintf("select rank from rankings where user_id = %d and scholarship_id = %d and criterion = '%s'", $user_id, $scholarship_id, $criterion);
		$r = $this->db->query($sql);
		$ret = $r->numRows() > 0 ? $r->fetchRow(DB_FETCHMODE_ORDERED) : array(0);
		return $ret[0];
	}
		
	function GetPhase2Rankings($id) {
		return $this->db->getAll('select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.scholarship_id = ? and r.criterion in ("onwiki","offwiki","future")', array($id));
	}

	function UpdateNotes($id, $notes) {
		$this->db->query("update scholarships set notes = ? where id = ?", array($notes, $id));
	}

	function UpdateField($field, $id, $value) {
		$query = "update scholarships set " . $field . " = ? where id  = ?";
		$this->db->query($query, array($value, $id));
	}

// Bulk mailing

	function GetPhase1EarlyRejects($start, $howmany) {
		$res = $this->db->getAll("select s.id, s.fname, s.lname, s.email, s.exclude, coalesce(p1score,0) as p1score from scholarships s 
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id					     
			group by s.id, s.fname, s.lname, s.email 
			having p1score < 3 and s.exclude = 0
			limit ?,?", array($start, $howmany));
		if (PEAR::isError($res)) 
   			die($res->getMessage());
    		return $res;
	}

// Final scoring 

	function GetFinalScoring($partial) {
		$res = $this->db->getAll("select s.id, s.fname, s.lname, s.email, s.residence, s.exclude, s.sex, 2011-year(s.dob) as age, (s.canpaydiff*s.wantspartial) as partial, c.country_name, coalesce(p1score,0) as p1score, coalesce(nscorers,0) as nscorers, r.onwiki as onwiki, r2.offwiki as offwiki, r3.future as future, 0.5*r.onwiki + 0.15*r2.offwiki + 0.35*r3.future as p2score from scholarships s 
			left outer join (select scholarship_id, avg(rank) as onwiki from rankings where criterion = 'onwiki' group by scholarship_id) r on s.id = r.scholarship_id
			left outer join (select scholarship_id, avg(rank) as offwiki from rankings where criterion = 'offwiki' group by scholarship_id) r2 on s.id = r2.scholarship_id
			left outer join (select scholarship_id, avg(rank) as future from rankings where criterion = 'future' group by scholarship_id) r3 on s.id = r3.scholarship_id
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r4 on s.id = r4.scholarship_id
			left outer join (select scholarship_id, count(distinct user_id) as nscorers from rankings where criterion in ('onwiki','offwiki', 'future') group by scholarship_id) r5 on s.id = r5.scholarship_id			
			left outer join countries c on s.residence = c.id      
			group by s.id, s.fname, s.lname, s.email, s.residence 
			having p1score >= 3 and s.exclude = 0 and partial = ?
			order by p2score desc", array($partial));
		if (PEAR::isError($res)) 
   			die($res->getMessage());
    		return $res;
	}
		
// User administration

	function GetUser($username) {
		return $this->db->query('select id, username, password, email, reviewer from users where username = ? and isvalid = 1', array($username))->fetchRow();
	}

	function GetUsername($id) {
		return $this->db->query('select username from users where id = ?', array($id))->fetchRow();
	}
		
	function GetListofUsers($state) {
		switch ($state) {
			case "all":
				return $this->db->getAll("select * from users");
				break;
			case "reviewer": 
				return $this->db->getAll("select * from users where `reviewer` = 1");
				break;
		}
	}

	function GetUserInfo($user_id) {
		return $this->db->query("select * from `users` where `id` = ?", array($user_id))->fetchrow();
	}

	function IsSysAdmin($user_id) {
		$sql = sprintf("select `isadmin` from `users` where `id` = %d", $user_id);
		$res = $this->db->query($sql)->fetchRow();
		if ( $res['isadmin'] == '1' ) {
			return true;
		} 
		return false;
	}

	function NewUserCreate($answers) {
		$res = $this->db->query("select `username` from `users` where `username` = ?", array($answers['username']));
		if( $res->numRows() < 1 )  {
			$fieldnames = array("username","password","email","reviewer","isvalid","isadmin");
			$this->db->query(
				sprintf("insert into users (%s) values ('%s')", join($fieldnames, ', '), join($answers, "', '")));
			return true;
		} else {
			return false;
		}
	}

	function UpdateUserInfo($answers, $id) {
		$fieldnames = array("username","email","reviewer","isvalid","isadmin","blocked");
		$query = "update users set ";
		for ($i=0 ; $i <= 5 ; $i++) {
			$field = $fieldnames[$i];
			$query .= ($i==0) ? '' : ',';
			$query .= '`' . $field . '`="' . $answers[$field] . '" ';
		}
		$query .= "where `id` = ?";
		$this->db->query($query, array($id));
	}

	function UserIsBlocked($id) {
		$query = "SELECT blocked FROM users WHERE id = ?";
		$userid = sprintf('%d', $id);
		$res = $this->db->query($query, $userid)->fetchRow();
		return $res['blocked'];
	}
	
	function UpdatePassword($oldpw, $newpw, $id, $force = NULL) {
		if ($force==1) {
			$this->db->query("update users set password = ? where id = ?", array(md5($newpw),$id));
			return 1;
		} else {
			$userdata = $this->db->query('select password from users where id = ?', array($id))->fetchRow();
			if ($userdata['password'] == md5($oldpw)) {
				$this->db->query("update users set password = ? where id = ?", array(md5($newpw),$id));
				return 1;
			} else {
				return 0;
			}
		}
	}

// Country administration
		
	function GetListofCountries($order = "country_name") {
            return $this->db->getAll("select c.id, c.country_name, c.region, c.country_rank, s.sid from countries c left join (select count(`id`) as sid, residence as attendees from scholarships where rank = 1 and exclude = 0 group by residence) s on c.id = s.attendees order by ?;", array($order));
	}
	
	function UpdateCountryRank($id, $newrank) {
		$this->db->query("update countries set country_rank = ? where id = ?", array($newrank, $id));
	}

	function GetCountryInfo($country_id) {
		return $this->db->query("select * from `countries` where `id` = ?", array($country_id))->fetchrow();
	}

	function GetListofRegions() {
		return $this->db->getAll("select count(*) as count, c.region from scholarships s LEFT JOIN countries c on c.id = s.residence group by region;");
	}

		
}
?>
