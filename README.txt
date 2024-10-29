=== ArvanCloud Object Storage ===
Contributors: arvancloud, khorshidlab
Tags: storage, s3, offload, backup, files, arvancloud
Requires at least: 4.0
Tested up to: 6.2
Requires PHP: 7.1
Stable tag: 1.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

ArvanCloud Storage for offload, backup and upload your WordPress files and databases directly to your ArvanCloud object storage bucket.


== Description ==
Using ArvanCloud Storage Plugin you can offload, backup and upload your WordPress files and databases directly to your ArvanCloud object storage bucket. This easy-to-use plugin allows you to back up, restore and store your files simply and securely to a cost-effective, unlimited cloud storage. No need for expensive hosting services anymore.


== Installation ==
= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'ArvanCloud Object Storage'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select 'arvancloud-object-storage.zip' from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `arvancloud-object-storage.zip`
2. Extract the `arvancloud-object-storage` directory to your computer
3. Upload the `arvancloud-object-storage` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

== Screenshots ==
1. 1 
2. 2
3. 3
4. 4
5. 5
6. 6

== Changelog ==
= 1.7.0 - 2024-09-02 =
* feature: Fetch files directly from your buckets with the object URL to your WordPress Media.
* feature: Add WooCommerce integration

= 1.5.0 - 2024-07-17 =
* feature: Add sub directories to the buckets based on the default WordPress media folder structure 
* feature: Bucket filter in Media list
* feature: New bucket info colmun in Media list

= 1.4.1 - 2024-05-05 =
* fix: Fix for APIKey expiration or change

= 1.4.0 - 2024-04-27 =
* feature: Add support for machine user connection
* Some minor improvement

= 1.3.1 - 2024-02-04 =
* perf: Better validation for file extention option and remove dots
* pref: Preserve last state of Local Save feature option
* Some minor improvement

= 1.3.0 - 2024-02-04 =
* feature: Add custom region to config
* feature: Ability to filter uploads with file extensions and size
* Some minor improvement

= 1.2.3 - 2023-08-11 =
* fix: Check for thumbnails file existence
* Some minor improvement

= 1.2.2 - 2023-07-18 =
* Some minor improvement on S3, integrations and scheduler

= 1.2.1 - 2023-06-25 =
* Fix a conflict (video upload process) with Arvan VOD Plugin

= 1.2.0 - 2023-06-12 =
* Add new feature: Delete object from storage when deleted in WordPress

= 1.1.0 - 2023-05-21 =
* Add Action Scheduler in admin menu
* Perform a periodic api validation once a day
* Some minor security improvement

= 1.0.3 - 2023-04-18 =
* Update support links
* Some minor improvement

= 1.0.1 - 2023-04-11 =
* Update dev dependencies
* Update WordPress plugin assets

= 1.0.0 - 2023-02-27 =
* Complete redesign and better user experience
* Add the count of objects in the selected bucket in Settings Page
* Add usage of the selected bucket in Settings Page
* Add the ability to create new buckets
* Add the ability to migrate from one bucket to another bucket
* Add Bulk upload feature: Send all local media files to the desired bucket at once
* Add the ability to delete all local files
* Add Empty bucket feature: Delete all the files in the selected bucket
* Add Download feature: Get all the files from the desired bucket at once and save them locally
* Add a Background Processing feature to handle a large number of files and bulk actions
* Fix the connection issue due to S3 region
* Better validation at the Object Storage credential process

= 0.9.3 - 2022-12-04 =
* Update AWS SDK
* Update WordPress assets

= 0.9.1 - 2022-06-20 =
* Update dependencies

= 0.9 - 2022-05-19 =
* Support favicon and custom_logo when uploading with customizer

= 0.8 - 2022-04-27 =
* Add System info feature

= 0.7 - 2022-02-16 =
* perf: Better validation config methods
* docs: Update pot and fa translation
* refactor: Checking keep-local-files is set or not

= 0.6 - 2022-02-11 =
* Tested up to 5.9
* Update assets

= 0.5 - 2022-01-11 =
* Fix keep local files option issue

= 0.4 - 2022-01-03 =
* Fix setting slug bug in Persian translation
* Fix rendering copy to bucket metabox in attachment 
* Minor options improvement

= 0.3 - 2021-11-27 =
* Minor changes

= 0.2 - 2021-11-25 =
* Update README

= 0.1 - 2021-11-25 =
* Official Plugin Release
