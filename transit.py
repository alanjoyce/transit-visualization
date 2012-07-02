#!/usr/bin/env python
# encoding: utf-8
"""
transit.py

Created by Alan Joyce on 2011-12-03.
Copyright (c) 2011 Alan Joyce. All rights reserved.
"""

import sys
import os
import json
import transitfeed


def main(argv=None):
	if argv is None:
		argv = sys.argv
	if len(argv) != 2:
		print "Usage: transit.py input_path"
		return
	else:
		input_path = argv[1]
	
	loader = transitfeed.Loader(input_path)
	schedule = loader.Load()
	
	trips = []
	
	for trip_id, tripInfo in schedule.trips.items():
		trip = {}
		trip['id'] = tripInfo.trip_headsign
		
		serviceInfo = schedule.GetServicePeriod(tripInfo.service_id)
		trip['monday'] = serviceInfo.monday
		trip['tuesday'] = serviceInfo.tuesday
		trip['wednesday'] = serviceInfo.wednesday
		trip['thursday'] = serviceInfo.thursday
		trip['friday'] = serviceInfo.friday
		trip['saturday'] = serviceInfo.saturday
		trip['sunday'] = serviceInfo.sunday
		
		trip['stops'] = []
		stoptimes = tripInfo.GetStopTimes()
		for i in range(len(stoptimes)):
			stopTime = stoptimes[i]
			stopID = stopTime.stop_id
			stopInfo = schedule.stops[stopID]
			
			stop = {}
			stop['id'] = stopID
			stop['name'] = stopInfo.stop_name
			stop['time'] = stopTime.arrival_secs
			stop['lat'] = stopInfo.stop_lat
			stop['lon'] = stopInfo.stop_lon
			trip['stops'].append(stop)
		
		trips.append(trip)
	
	outF = open(input_path + "_out.js", "w")
	outF.write("var data = ")
	json.dump(trips, outF)


if __name__ == '__main__':
	main()

