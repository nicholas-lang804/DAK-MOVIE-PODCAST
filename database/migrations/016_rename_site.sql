-- Rename the site to "THE MOVIE ZONE PODCAST 256" in an existing database.
-- (New installs get this name from the seed files automatically.)
-- You can also change it later in Admin > Settings > Site Name.
UPDATE site_settings SET setting_value = 'THE MOVIE ZONE PODCAST 256' WHERE setting_key = 'site_name';
