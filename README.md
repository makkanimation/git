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

## Reseting Conflict Merge
	If you dont want to merge the changes and still want to update your local then run:

	 - git reset --hard HEAD  
	This will reset your local with HEAD and then pull your remote using git pull.

	If you've already committed your merge locally (but haven't pushed to remote yet), and want to revert it as well:

	-  git reset --hard HEAD~1 
	https://stackoverflow.com/questions/26376832/why-does-git-say-pull-is-not-possible-because-you-have-unmerged-files


## Fix error error: pathspec '' did not match any file(s) known to git
https://www.youtube.com/watch?v=dTkHmcJsCvM

## Reset git add selected file : How do I undo 'git add' before commit?
	git reset <file>
 	https://stackoverflow.com/questions/348170/how-do-i-undo-git-add-before-commit


For All the Commands Below
--------------------------

The commands below assume you've navigated to the folder for the Git repo.

### See What Branch You're On

*   Run this command:
    *   **git status**

### List All Branches

**NOTE:** The current local branch will be marked with an asterisk (\*).

*   To see **local branches**, run this command:
    *   **git branch**
*   To see **remote branches**, run this command:
    *   **git branch -r**
*   To see **all local and remote branches**, run this command:
    *   **git branch -a**

### Create a New Branch

*   Run this command (replacing **my-branch-name** with whatever name you want):
    *   **git checkout -b my-branch-name**
*   You're now ready to commit to this branch.

### Switch to a Branch In Your Local Repo

*   Run this command:
    *   **git checkout my-branch-name**

### Switch to a Branch That Came From a Remote Repo

1.  To get a list of all branches from the remote, run this command:
    *   **git pull**
2.  Run this command to switch to the branch:
    *   **git checkout --track origin/my-branch-name**

### Push to a Branch

*   If your local branch **does not exist** on the remote, run either of these commands:
    *   **git push -u origin my-branch-name**
    *   **git push -u origin HEAD**

**NOTE:** HEAD is a reference to the top of the current branch, so it's an easy way to push to a branch of the same name on the remote. This saves you from having to type out the exact name of the branch!

*   If your local branch **already exists** on the remote, run this command:
    *   **git push**

### Merge a Branch

1.  You'll want to make sure your working tree is clean and see what branch you're on. Run this command:
    *   **git status**
2.  First, you must check out the branch that you want to merge another branch into (changes will be merged into this branch). If you're not already on the desired branch, run this command:
    *   **git checkout master**
    *   **NOTE:** Replace **master** with another branch name as needed.
3.  Now you can merge another branch into the current branch. Run this command:
    *   **git merge my-branch-name**
    *   **NOTE:** When you merge, there may be a conflict. Refer to **Handling Merge Conflicts** (the next exercise) to learn what to do.

### Delete Branches

*   To delete a **remote branch**, run this command:
    *   **git push origin --delete my-branch-name**
*   To delete a **local branch**, run either of these commands:
    *   **git branch -d my-branch-name**
    *   **git branch -D my-branch-name**
*   **NOTE:** The -d option only deletes the branch if it has already been merged. The -D option is a shortcut for --delete --force, which deletes the branch irrespective of its merged status.
