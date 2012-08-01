 
strt importing gpx files ...

<?php 
	require_once 'nikeplusphp.4.1.2.php';
	require_once 'nikeplusphptogpx.php';
	if($argv[1] == NULL || $argv[2] == NULL) {
		exit( "No Username and/or password given; Usage is: php NikeToGpx username password \n \n");
	}
	$n = new NikePlusPHPGpxExport(trim($argv[1]), trim($argv[2]));
	$dir = "import";
	if(!is_dir($dir)) {
		mkdir($dir);
	}
	$runs = $n->activities();
	if(count($runs->activities) == 0) {
		exit( "Incorrect Username and/or password given; Make sure you use your email as username \n \n");
	}
	foreach($runs->activities as $a) {
		$runId = $a->activityId;
		$name = $a->name;
		$name = preg_replace('/\s+/', '', $name);
		$name = str_replace('/', ':', $name);
		$name= substr($name,6,strlen($name)-13);
		$fileName = sprintf('%s/%s-%s.gpx', $dir, $runId, $name);
		if(!file_exists($fileName)) {                     
			echo "importing run " . $fileName . "\n";
			$run = $n->run($runId);	

			if($run === NULL) {
				echo "cannot import " . $runId . "\n";
				continue;                    
		    }
			file_put_contents($fileName, $n->toGpx($run)); 
		}
	}
?>

eof