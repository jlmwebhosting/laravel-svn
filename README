laravel-svn
================================

Description
--------------------------------
This library is destined to plug one annoying gap currently present in the interaction between Laravel and the PHP SVN PECL extension: error handling. The original PECL lib throws warnings, which Laravel catches and halts code execution with. All methods are written to get rid of this as much as possible, and provide an OOP, extension-driven error handling schema instead.
(It also allows you to neatly wrap up your SVN repos. Which, in itself, is an added bonus)

Usage
================================
Initiating a repository
--------------------------------
Exporting/Opening a repository is done simply by the constructor of the library, as follows:

    ```$repo = new SebRenauld\SVN("/path/to/my/repo","my_username","my_password");```

If the repository directory does not exist, it will be created. To set the repo URL, immediately call the following:

    ```$repo->setRepository("http://path.to.my/svn/repo/");```

This will check out the repo, but only if the directory specified in the constructor is not already a working copy.

Once this is done, the following functions are open to use/exposed. The checkmarks mark whether I have mapped all the possible warnings/errors based on them (PECL SVN is completely undocumented as of now).
- [ ] ```update($paths=array(), $revisionNumber=SVN_REVISION_HEAD)`: Updates the given paths (relative to root) to the specified revision
- [x] ```log($path="", $revisionFrom=SVN_REVISION_HEAD, $revisionTo=SVN_REVISION_INITIAL)```: Fetches all changes/commit logs for the given path between given revisions
- [x] ```info($path)```: Retrieves the status of the file in the working copy (modified, deleted, etc)
- [x] ```add_file($path)```: Marks a file to be added on the next commit
- [x] ```delete_file($path)```: Marks a file for deletion
- [ ] ```commit($CommitLogMessage, $paths=array())```: Executes a commit

In addition, the following handy methods are present:
- [x] ```modifications($path)``` is an re-formatted alias of ```info($path)```. Instead of returning the (clunky) original format, it will return an stdClass object containing the following properties:
 * `modified`: Boolean, true if the file in the working copy has been modified
 * `path`: Relative path to the file
 * `added`: Boolean, true if the file in the working copy is new
 * `deleted`: Boolean, true if the file has been deleted
- [ ] ```update_all()``` is an alias of ```update("")```

If a command throws a warning, the library will convert it to an instance of SebRenauld\SVNException containing the highest-priority (statically defined in the exception class) error that came up, as PECL SVN groups all errors related to a commit. Help me map those errors if one goes through!

Feel free to fork, extend, and request for pulls. This is by no means final, and meant to be extended!
