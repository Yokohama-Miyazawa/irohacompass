<?php $this->Html->scriptStart(array('inline' => false)); ?>
	switch(window.location.host)
	{
		case 'yuizono.irohasoft.com':
		{
			is_target = true;
		}
	}
	
	if (
		(is_target)&&
		(document.location.protocol==="http:")
	)
	{
		location.replace('https://'+window.location.host+window.location.pathname);
	}
<?php $this->Html->scriptEnd(); ?>
<?php $this->start('css-embedded'); ?>
	<style type='text/css'>
		#remember_me
		{
			margin-left	: 10px;
		}
	</style>
<?php $this->end(); ?>
<div class="users form">
	<div class="panel panel-info form-signin">
		<div class="panel-heading">
			学習者ログイン
		</div>
		<div class="panel-body">
			<div class="text-right"><a href="../admin/users/login">管理者ログインへ</a></div>
			<?php echo $this->Flash->render('auth'); ?>
			<?php echo $this->Form->create('User'); ?>
			
			<div class="form-group">
				<?php echo $this->Form->input('username', array('label' => __('ログインID'), 'class'=>'form-control', 'value' => $username)); ?>
			</div>
			<div class="form-group">
				<?php echo $this->Form->input('password', array('label' => __('パスワード'), 'class'=>'form-control', 'value' => $password));?>
				<input type="checkbox" name="data[User][remember_me]" checked="checked" value="1" id="remember_me"><?php echo __('ログイン状態を保持')?>
				<?php echo $this->Form->unlockField('remember_me'); ?>
			</div>
			<?php echo $this->Form->end(array('label' => 'ログイン', 'class'=>'btn btn-lg btn-primary btn-block')); ?>
		</div>
	</div>
</div>