# git

## How can i save username and password in GIT
https://stackoverflow.com/questions/35942754/how-can-i-save-username-and-password-in-git

To setup MFG platform on local
========================================================================================
	- Create an new instance 
		git clone  giturl ./<project_floder_name>

	- CD <project_floder_name>

	- To install dependency 
		composer Install

	- Remove this line from .htaccess if https not enable
			#RewriteCond %{HTTPS} !=on
			#RewriteRule ^(.*)$ http://%{HTTP_HOST}%{REQUEST_URI} [L,R=301,NE]
		and then run 
			- git update-index --assume-unchanged .htaccess

	- Update ".env" as per project

	
Usefull Command
========================================================================================
	- composer dump-autoload
	- git update-index --assume-unchanged .htaccess
	- php artisan optimize 
	. git pull origin main ## if someone is working on git hub, and we need to download again

Deploy to Server
=========================================================================================
	- To pull from GIT server
	
		- git pull origin main

	- To show changes 
	
		- git status 

	- To add files/Folders
	
		-git add .

	- To Commit
	
		- git commit -m "Meanfully Comment"

	- To push to GIT sever
	
		- git push origin main

============================================================

	- To temporary remove all current change
	
		- git stash
		
	- To Get lastest stash 
	
		- git stash pop
