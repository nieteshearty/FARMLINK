-- Update FREDREX SALAC with Naval, Biliran coordinates
-- Naval, Biliran, Philippines: 11.5682° N, 124.4133° E

UPDATE users 
SET latitude = 11.5682, longitude = 124.4133 
WHERE username = 'FREDREX SALAC' AND role = 'farmer';

-- Verify the update
SELECT username, farm_name, location, latitude, longitude 
FROM users 
WHERE username = 'FREDREX SALAC' AND role = 'farmer';

-- Optional: Add coordinates for other farmers in Naval, Biliran area
-- (You can run these if you want more farmers with locations)

-- UPDATE users 
-- SET latitude = 11.5695, longitude = 124.4145 
-- WHERE role = 'farmer' AND location LIKE '%Naval%' AND latitude IS NULL;
