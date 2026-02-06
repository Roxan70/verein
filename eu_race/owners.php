<?php
require_once __DIR__ . '/inc/header.php'; require_login(); require_role(array('admin','organizer','viewer'));
$canEdit = in_array($_SESSION['role'], array('admin','organizer'), true);
if ($canEdit && $_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['owner_id']??0); $name=trim($_POST['name']??''); $address=trim($_POST['address']??''); $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $country=trim($_POST['country_code']??'AT');
    if($id>0){$st=$mysqli->prepare('UPDATE owners SET name=?,address=?,phone=?,email=?,country_code=? WHERE owner_id=?');$st->bind_param('sssssi',$name,$address,$phone,$email,$country,$id);} else {$st=$mysqli->prepare('INSERT INTO owners (name,address,phone,email,country_code) VALUES (?,?,?,?,?)');$st->bind_param('sssss',$name,$address,$phone,$email,$country);} $st->execute();$st->close();
}
$q=trim($_GET['q']??''); $page=get_page(); $off=get_offset($page,PAGE_SIZE); $like='%'.$q.'%';
$st=$mysqli->prepare('SELECT owner_id,name,address,phone,email,country_code FROM owners WHERE (?="" OR name LIKE ? OR email LIKE ?) ORDER BY owner_id DESC LIMIT ? OFFSET ?');
$st->bind_param('sssii',$q,$like,$like,$ps=PAGE_SIZE,$off);$st->execute();$st->bind_result($oid,$name,$address,$phone,$email,$country);
?>
<?php if($canEdit): ?><div class="card"><form method="post" class="row"><div class="col"><input type="hidden" name="owner_id" value="0"><label>Name<input name="name" required></label></div><div class="col"><label>Address<input name="address"></label></div><div class="col"><label>Phone<input name="phone"></label></div><div class="col"><label>Email<input name="email"></label></div><div class="col"><label>Country<input name="country_code" value="AT"></label></div><div class="col"><button><?php echo e(t('save')); ?></button></div></form></div><?php endif; ?>
<div class="card"><form><input name="q" value="<?php echo e($q); ?>" placeholder="Search"><button>Filter</button></form></div>
<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Country</th></tr><?php while($st->fetch()): ?><tr><td><?php echo (int)$oid; ?></td><td><?php echo e($name); ?></td><td><?php echo e($email); ?></td><td><?php echo e($phone); ?></td><td><?php echo e($country); ?></td></tr><?php endwhile; ?></table>
<?php $st->close(); require_once __DIR__ . '/inc/footer.php'; ?>
