<?php
require_once('init.php');

session_start();

if (!isset($_SESSION['user_id']))
{
	header('location: ' . $BASEURL . 'user/login');
	exit();
}

$dal = new DataAccessLayer();
$schols = $dal->GetPhase1EarlyRejects();

if($_GET["action"]=="export"){
	header('Content-type: text/download; charset=utf-8');
	header('Content-Disposition: attachment; filename="p1fail_' . gmdate('ymd_Hi', time() ) . '.csv"');
	echo "id,name,email,p1score\n";
	foreach ($schols as $row):
		echo $row['id'].','.$row['fname'] . ' ' . $row['lname'].','.$row['email'].','.round($row['p1score'],4)."\n";
	endforeach;
	return;
}

$ctr=1;	
?>
<?php include TEMPLATEPATH . "header_review.php" ?>
<style>
table, td, th
{
border:1px solid black;
}
</style>

<form method="get" action="<?php echo $BASEURL; ?>review/p1/failList">
<h1>Phase 1 - Fail List</h1>
<?php include TEMPLATEPATH . "admin_nav.php" ?>
<input type="hidden" name="action" value="export">
<input type="submit" value="Export list">
<p></p>
<table style="width: 100%;" border="1">
	<tr>
		<th>counter</th>
		<th>id</th>
		<th>name</th>
		<th>email</th>
		<th>p1 score</th>
	</tr>
	<?php foreach ($schols as $row): ?>
	<tr>
		<td width=20%><?= $ctr++; ?></td>
		<td width=20%><?= $row['id']; ?></td>
		<td width=20%><a href="../view.php?id=<?= $row['id'] ?>&phase=0" target="_blank"><?= $row['fname'] . ' ' . $row['lname']; ?></a></td>
		<td width=20%><?= $row['email']; ?></td>
		<td width=20%><?= round($row['p1score'],4); ?></td>
	</tr>
	<?php endforeach; ?>
</table>
</form>
<?php include TEMPLATEPATH. "footer_review.php" ?>
