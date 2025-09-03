sahtout_site: the website database

Run the other SQL files :example 
-----acore_auth_sahtout_site.sql------ 
means thats inside the acore_auth database



⚠️ Important Database Migration Notice ⚠️

The new sahtout_site SQL file will recreate the database and all tables.
That means it will delete your old structure.

To avoid losing your data:

1.Export/backup your current sahtout_site database.

2.Run the new SQL file to install the updated database structure.

3.Manually re-insert or import your old data into the new database.

👉 Without step 1, all your data will be lost when applying the new version.