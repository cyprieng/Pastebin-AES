<html>
	<head>
		<title>Pastebin with AES</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style type="text/css">
			*{margin:0;padding:0;}
			body{
				font-family: Arial, Helvetica, "Liberation Sans", FreeSans, sans-serif;
				font-size:14px;
				color:#727272;

				background:url("img/background.png");
				padding-bottom:20px;

				word-wrap: break-word;  
			}
			form{
				padding-top:5%;
				margin:auto;
				width:80%;
			}
			form h1 a{
				color:#727272;
				text-decoration:none;
			}
			textarea{
				height:300px;
				width:100%;
				margin:auto;

				font-family: Arial, Helvetica, "Liberation Sans", FreeSans, sans-serif;
				font-size:14px;
				color:#727272;
			}
			input[type="password"]{
				width:100%;
			}
			input[type="submit"]{
				padding:5px;
			}
			form a{
				color:#727272;
			}
		</style>
	</head>

	<body>
		<form action="index.php" method="post">
			<h1><a href="index.php">Pastebin with AES</a></h1><br/><br/>

			<label for="expire">Expires: </label>
			<select name="expire" id="expire">
				<option value="5min" >5 min</option>
				<option value="10min" >10 min</option>
				<option value="1hour" >1 hour</option>
				<option value="1day" >1 day</option>
				<option value="1week" >1 week</option>
				<option value="1month" >1 month</option>
				<option value="1year" >1 year</option>
				<option value="never" >Never</option>
				<option value="burnafterreading" >Burn after reading</option>
			</select>

			<br/><br/>

			<?php
				//Get crypted text and delete file
				if(!empty($_GET["decrypt"])){
					if(is_file("./data/".$_GET["decrypt"])){
						//Get json
						$file = fopen("./data/".$_GET['decrypt'], "r");
						$json = json_decode(fgets($file), true);
						fclose($file);

						if($json['expire_date'] == "burnafterreading"){ //Burn after reading
							unlink("./data/".$_GET["decrypt"]);
							$text = $json["ciphertext"];
						}
						else if($json['expire_date'] == "never"){ //Never expires
							$text = $json["ciphertext"];	
						}
						else if($json["expire_date"] < time()) unlink("./data/".$_GET["decrypt"]); //Expired
						else $text = $json["ciphertext"]; //Not expired yet
					}
				}
			?>			

			<textarea name="toCrypt"><?php if(!empty($text)) echo $text; ?></textarea><br/><br/>
			<input name="key" type="password" placeholder="Encryption key" /><br/><br/>
			<input type="submit" name="encrypt" value = "Crypt" />
			<input type="submit" name="decrypt" value = "Decrypt" />

			<br/><br/>

			<?php
				//Generate random file name
				function generateRandomString($length = 10){
					$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
					$randomString = '';
					for ($i = 0; $i < $length; $i++) {
						$randomString .= $characters[rand(0, strlen($characters) - 1)];
					}
					return $randomString;
				}

				if(!empty($_POST["toCrypt"]) && !empty($_POST["key"]) && !empty($_POST["expire"])){ //Crypt
					if(!empty($_POST['encrypt'])){
						//Create hexa key of 32 bytes
						$key = pack('H*', hash('sha256', $_POST["key"]));

						//Create random IV to use with CBC encryption
						$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
						$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

						//Set encoding of encrypt text
						$plaintext_utf8 = utf8_encode(nl2br(htmlspecialchars($_POST["toCrypt"])));

						//Create cipher text compatile with AES
						//Don't work with text ending with 00h because of auto delte of final 0
						$ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key,
						                             $plaintext_utf8, MCRYPT_MODE_CBC, $iv);

						//Add IV
						$ciphertext = $iv . $ciphertext;

						$ciphertext_base64 = base64_encode($ciphertext);

						//Create json
						$data['ciphertext'] = $ciphertext_base64;
						$data['postdate'] = time();

						if($_POST['expire'] == "5min") $data['expire_date'] = time() + (5*60);
						else if($_POST['expire'] == "10min") $data['expire_date'] = time() + (10*60);
						else if($_POST['expire'] == "1hour") $data['expire_date'] = time() + (60*60);
						else if($_POST['expire'] == "1day") $data['expire_date'] = time() + (24*60*60);
						else if($_POST['expire'] == "1week") $data['expire_date'] = time() + (7*24*60*60);
						else if($_POST['expire'] == "1month") $data['expire_date'] = time() + (30*24*60*60);
						else if($_POST['expire'] == "1year") $data['expire_date'] = time() + (365*24*60*60);
						else if($_POST['expire'] == "never") $data['expire_date'] = "never";
						else if($_POST['expire'] == "burnafterreading") $data['expire_date'] = "burnafterreading";

						$json = json_encode($data);

						//Create file name
						$name_available = false;
						$length = 10;
						while(!$name_available){
							$filename = generateRandomString($length);

							//Check if file already exists
							if(!is_file("./data/".$filename)) $name_available = true;

							$length++; //Increase length for each collision
						}

						//Create file
						$file = fopen("./data/".$filename, "a");
						fputs($file, $json);
						fclose($file);

						//Show link and crypted text
						$url = pathinfo("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
						echo  "<strong>Encrypted text:</strong> <a href=\"". $url["dirname"] . DIRECTORY_SEPARATOR ."?decrypt=". urlencode($filename) ."\" />Share link</a><br/>" . $ciphertext_base64;
					}
					else if(!empty($_POST['decrypt'])){ //Decrypt
						//Get key and cypher text
						$key = pack('H*', hash('sha256', $_POST["key"]));
						$ciphertext_dec = base64_decode($_POST["toCrypt"]);

						//Get IV
						$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
						$iv_dec = substr($ciphertext_dec, 0, $iv_size);

						//Get decrypted text
						$ciphertext_dec = substr($ciphertext_dec, $iv_size);
						$plaintext_utf8_dec = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key,
						                                 $ciphertext_dec, MCRYPT_MODE_CBC, $iv_dec);

						echo  "<strong>Decrypted text:</strong> " . utf8_decode($plaintext_utf8_dec);
					}
				}
			?>
		</form>
	</body>
</html>
