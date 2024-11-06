<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'My Extension',
			'jquery3' => true,
			'jqueryui1' => true,
			'bootstrap4' => true,
			'fontawesome4' => true,
			'sbadmintemplate3' => true,
			'ajaxlib' => true,
			'navigationwidget' => true
		)
	);
?>

<body>
	<div id="wrapper">

		<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-lg-12">
						<h3 class="page-header">My Extension</h3>
					</div>
				</div>
				<div>
					This is the My Extension Template
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>

