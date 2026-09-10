-- Provider Dashboard schema extensions
-- review_status was part of the Ace702 provider_dashboard customization (not in core OpenEMR).

#IfMissingColumn forms review_status
ALTER TABLE `forms` ADD `review_status` TINYINT(1) NULL DEFAULT NULL COMMENT 'Provider dashboard review: null/0=Not Ready, 1=Ready/InProgress';
#EndIf

#IfNotRow2D list_options list_id default_open_tabs option_id pdb
INSERT INTO `list_options` (`list_id`, `notes`, `title`, `seq`, `option_id`, `activity`) VALUES ('default_open_tabs', 'interface/modules/custom_modules/oe-provider-dashboard/public/index.php', 'Provider Dashboard', 10, 'pdb', '1');
#EndIf
