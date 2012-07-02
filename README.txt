CS448B Final Project: Automated Visualization of Public Transportation Time Schedules
Alan Joyce (ajoyce)

-------- EXAMPLES --------

There are examples of this visualization online at the following URLs:

Caltrain: http://everythingdigital.org/other/transit/index.php?feed=caltrain
Metrolink: http://everythingdigital.org/other/transit/index.php?feed=metrolink
BART: http://everythingdigital.org/other/transit/index.php?feed=bart
SF Bay Ferries: http://everythingdigital.org/other/transit/index.php?feed=sfbay

(SF Muni is not made available due to its large source file size)

These examples work best in Google Chrome, or another WebKit-based browser.


-------- DEPENDENCIES --------

This project utilizes the following external libraries:

GoogleTransitDataFeed (Python): http://code.google.com/p/googletransitdatafeed/


-------- WALKTHROUGH --------

To visualize a time schedule, follow these steps:

1. Download the GTFS package for the transportation system that you intend
to visualize (a list of publicly available GTFS feeds is posted at
http://code.google.com/p/googletransitdatafeed/wiki/PublicFeeds).

2. Place the enclosing folder of the GTFS package in the root project
directory and run the transit.py Python script with the folder name
as an argument (example: "python transit.py myfeed").

3. The example in Step 2 will produce the file "myfeed_out.js", which is
then included as an external Javascript file in index.php. If your
transit feed name is not already accounted for in the PHP code
at the top of index.php and you want to specify a default starting location,
add the following line at the top of index.php:
	else if ($feed == "myfeed") $currentStop = "myStationID"
If you do not add this line, the visualization will pick the first station
listed in the GTFS package, which may be inaccessible or not in service.

4. Now, simply access "$PROJECT_FOLDER/index.php?feed=myFeed" in a web
browser and you will see a rendering of your transit data feed.

5. Use the mouse to click and drag and the scroll wheel to zoom in and out.
You can switch to the perspective of another station in the system by
clicking a stop icon associated with that station.

