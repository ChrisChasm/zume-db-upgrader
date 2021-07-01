## 1. Clean up trash in user meta table

```
# remove buddy press data
DELETE FROM wp_usermeta WHERE meta_key = 'bp_xprofile_visibility_levels'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_articles'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_attachment'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_bp-email'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_dashboard'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_dashboard-user'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_dt_webform_forms'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_locations'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_nav-menus'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_page'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_playbook'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_reports'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_site_link_system'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_toplevel_page_bp-groups'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_zume_download'; 
DELETE FROM wp_usermeta WHERE meta_key = 'closedpostboxes_zume_video'; 

DELETE FROM wp_usermeta WHERE meta_key = 'group_-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_20-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_210-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_213-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_252-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_264-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_362-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_365-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_400-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_459-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_52-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_56-session_9'; 
DELETE FROM wp_usermeta WHERE meta_key = 'group_93-session_9';

DELETE FROM wp_usermeta WHERE meta_key = 'wp_12_capabilities' AND user_id NOT IN (274, 2, 7, 26, 862, 4135, 6982);
DELETE FROM wp_usermeta WHERE meta_key = 'wp_12_user_level' AND user_id NOT IN (274, 2, 7, 26, 862, 4135, 6982);

DELETE FROM wp_usermeta WHERE meta_key = 'zume_active_group';
DELETE FROM wp_usermeta WHERE meta_key = 'zume_address';
DELETE FROM wp_usermeta WHERE meta_key = 'zume_ip_at_registration';
DELETE FROM wp_usermeta WHERE meta_key = 'ip_location_grid_meta';


?? ip_location_grid_meta
```


## 2. Upgrade to Location Grid v2
- DiscipleTools/location-grid-v2-upgrade

## 3. Upgrade theme with upgrades
- wppusher

## 4. Remove POEditor plugin
- remove poeditor plugin
- remove translations page

## set map key
pk.eyJ1IjoiY2hyaXNjaGFzbSIsImEiOiJjancxMTVjMDYwZHdxNDlwczkxcWtxc2VhIn0.DyM2FWHylzsw4ZlwoaNIZg

## 4. Convert ip addresses to standard location_grid_meta
- run upgrader plugin




## Convert old logs from early Zume versions

users = 21362
zume_groups_xxxxxxxx = 4431~


## 

1. visited content
2. registered
3. started a group





















