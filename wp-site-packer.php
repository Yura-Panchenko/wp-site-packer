<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);	

if ( PHP_SAPI !== 'cli' ) {
	echo 'This script can only be run from the command line!';
	exit;
}

/* Command example:
php wp-site-packer.php -u 'admin' -p '123'
*/

$arguments = getopt("u:p:");

require_once 'wp-load.php';

// https://gist.github.com/micc83/fe6b5609b3a280e5516e2a3e9f633675
$database = DB_NAME;
$user = DB_USER;
$pass = DB_PASSWORD;
$host = DB_HOST;
$dump_file = dirname(__FILE__) . '/' . $database . '.sql';

exec("mysqldump --user={$user} --password={$pass} --host={$host} {$database} --result-file={$dump_file} 2>&1", $output);

$source = 'wp-content';
$destination = 'stage_backup_' . date('Ymd') . '.zip';


$readme_text = "Please find the attached archive that contains the \"wp-content\" folder and the .sql file.

After downloading the archive extract its contents and follow the WordPress installation instructions provided below:

1. install a default WordPress site or backup the existing data on your server
2. copy the \"wp-content\" folder to the root folder of your WordPress site on your server
3. replace all instances of [SITEURL] in the .sql file with your real site URL and save the .sql file
4. import the database from the .sql file (please note that when importing all your existing data will be replaced with the data from the .sql file)
5. the admin panel access will be reset to user: [ADMIN] password: [PASSWORD]";

$readme_text = str_replace( '[SITEURL]', get_option( 'siteurl' ), $readme_text );

if ( ! empty( $arguments['u'] ) && ! empty( $arguments['p'] ) ) {
	$readme_text = str_replace( array( '[ADMIN]', '[PASSWORD]' ), array( $arguments['u'], $arguments['p'] ), $readme_text );
}


// https://gist.github.com/menzerath/4185113/72db1670454bd707b9d761a9d5e83c54da2052ac#gistcomment-3123786
$zip = new ZipArchive();
if($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
	$source = realpath($source);
	if(is_dir($source)) {
		$iterator = new RecursiveDirectoryIterator($source);
		$iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
		foreach($files as $file) {
			if ($file->getBasename() !== 'uploads.zip') {
				$file = realpath($file);
				if(is_dir($file)) {
					$zip->addEmptyDir('wp-content/' . str_replace($source . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
				} elseif(is_file($file)) {
					$zip->addFile($file, 'wp-content/' . str_replace($source . DIRECTORY_SEPARATOR, '', $file));
				}
			}
		}
	} elseif(is_file($source)) {
		$zip->addFile($source, 'wp-content/' . basename($source));
	}
	if ( file_exists( $dump_file ) ) {
		$zip->addFile($dump_file,basename($dump_file));
	}
}
$zip->addFromString('README.txt', $readme_text);
$zip->close();

if (file_exists($dump_file)) unlink($dump_file);

echo "Link to  download the archive - " . home_url( '/' . $destination ) . "\r\n";
?>