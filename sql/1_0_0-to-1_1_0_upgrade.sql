-- Task Manager branding (package 1.1.0 / v_database 2)

#IfNotRow2D list_options list_id default_open_tabs option_id pdb
INSERT INTO `list_options` (`list_id`, `notes`, `title`, `seq`, `option_id`, `activity`) VALUES ('default_open_tabs', 'interface/modules/custom_modules/oe-provider-dashboard/public/index.php', 'Task Manager', 5, 'pdb', '1');
#EndIf

#IfRow2D list_options list_id default_open_tabs option_id pdb
UPDATE `list_options` SET `title` = 'Task Manager' WHERE `list_id` = 'default_open_tabs' AND `option_id` = 'pdb' AND `title` = 'Provider Dashboard';
#EndIf
