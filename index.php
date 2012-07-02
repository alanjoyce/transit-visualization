<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">

<?php
	$feed = $_GET["feed"];
	if(!isset($_GET["feed"])) $feed = "caltrain";
	$filename = $feed . "_out.js";
	
	$currentStop = "";
	if($feed == "caltrain") $currentStop = "Palo Alto Caltrain";
	else if($feed == "bart") $currentStop = "CIVC";
	else if($feed == "sfmta") $currentStop = "5063";
	else if($feed == "metrolink") $currentStop = "131";
	else if($feed == "sfbay") $currentStop = "1905";
	else $currentStop = ""
?>

<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Public Transportation Schedule</title>
	<meta name="author" content="Alan Joyce">
	
	<script type="text/javascript" src=<?php echo '"' . $filename . '"'?>></script>
	
	<style type="text/css">
		body {
			height: 100%;
			padding: 0;
			margin: 0;
			overflow: hidden;
		}
		
		#schedule {
			width: 100%;
			height: 100%;
		}
	</style>
</head>
<body>
	<canvas id="schedule" tabindex="2"></canvas>
</body>

<script type="text/javascript">
	var currentStop = <?php echo '"' . $currentStop . '"'?>;
	if(currentStop == "") {
		currentStop = data[0]["stops"][0]['id']
	}
	
	var weekdays = new Array(7)
	weekdays[0] = "Sunday"
	weekdays[1] = "Monday"
	weekdays[2] = "Tuesday"
	weekdays[3] = "Wednesday"
	weekdays[4] = "Thursday"
	weekdays[5] = "Friday"
	weekdays[6] = "Saturday"
	
	var angles = new Array(7)
	var i = 0
	for(var mult = 1; mult >= -5; mult--) {
		angles[i] = mult * Math.PI / 4
		i++
	}
	
	var occupied = new Array(7)
	for(var i = 0; i < occupied.length; i++) {
		occupied[i] = -1
	}
	
	var rightPref = new Array(7)
	rightPref[0] = 1
	rightPref[1] = 2
	rightPref[2] = 0
	rightPref[3] = 3
	rightPref[4] = 4
	rightPref[5] = 6
	rightPref[6] = 5
	
	var leftPref = new Array(7)
	leftPref[0] = 5
	leftPref[1] = 4
	leftPref[2] = 6
	leftPref[3] = 3
	leftPref[4] = 2
	leftPref[5] = 0
	leftPref[6] = 1
	
	var neutralPref = new Array(7)
	neutralPref[0] = 3
	neutralPref[1] = 1
	neutralPref[2] = 5
	neutralPref[3] = 2
	neutralPref[4] = 4
	neutralPref[5] = 0
	neutralPref[6] = 6
	
	var clickTargets = new Array()
	var highlightedStop
	
	var colors = new Array(7)
	colors[0] = "#E6C377"
	colors[1] = "#90A6E8"
	colors[2] = "#EEB4F0"
	colors[3] = "#BBEDA1"
	colors[4] = "#B4E6F0"
	colors[5] = "#99E09A"
	colors[6] = "#F0B4B8"
	
	var demoMode = false
	var currentTime = getCurrentTime()
	var currentDay = getCurrentDay()
	
	var bestTimes = null
	var goodRoutes = null
	var lastUpdate = 0
	var refreshTime = 10
	if(demoMode) refreshTime = 0.1
	
	var canvas = document.getElementById("schedule")
	var context = canvas.getContext("2d")
	
	var width = canvas.width = canvas.clientWidth
	var height = canvas.height = canvas.clientHeight
	var horizon = getHorizon()
	var initZoom = 350.0
	var zoomLevel = initZoom
	
	var centerX = width / 2
	var centerY = height / 2
	
	var originalMiddleDotR = 40.0
	var middleDotR = originalMiddleDotR
	
	window.onresize = drawSchedule
	canvas.addEventListener("mousewheel", handleZoom, true)
	canvas.onmousedown = startDrag
	canvas.onmousemove = doDrag
	canvas.onmouseup = canvasMouseUp
	canvas.ondblclick = goToCenter
	var dragHappening = false
	var prevCenterX = centerX
	var prevCenterY = centerY
	var dragStartX = 0
	var dragStartY = 0
	
	drawSchedule()
	
	
	function handleZoom(event) {
		var change = 0
		
		if(event.wheelDelta) {
			change = -event.wheelDelta / 3
		}
		
		if(change) {
			zoomLevel += change
			if(zoomLevel < 200) zoomLevel = 200
			if(zoomLevel > 1300) zoomLevel = 1300
			
			drawSchedule()
		}
	}
	
	
	function startDrag(event) {
		prevCenterX = centerX
		prevCenterY = centerY
		dragStartX = event.clientX
		dragStartY = event.clientY
		dragHappening = true
	}
	
	function doDrag(event) {
		if(dragHappening && event.button <= 1) {
			centerX = prevCenterX + (event.clientX - dragStartX)
			centerY = prevCenterY + (event.clientY - dragStartY)
			drawSchedule()
		}
		
		//See if we're inside a stop
		var foundStop = false
		for(var i = 0; i < clickTargets.length; i++) {
			var target = clickTargets[i]
			if(Math.pow(event.clientX - target.x, 2) + Math.pow(event.clientY - target.y, 2) < Math.pow(target.r, 2)) {
				highlightedStop = target.stop
				foundStop = true
				canvas.style.cursor = "pointer"
				drawSchedule()
			}
		}
		
		//Clear any previously highlighted stop
		if(highlightedStop != null && !foundStop) {
			highlightedStop = null
			canvas.style.cursor = ""
			drawSchedule()
		}
	}
	
	function canvasMouseUp(event) {
		if(dragHappening) dragHappening = false
		
		//See if we clicked a stop
		for(var i = 0; i < clickTargets.length; i++) {
			var target = clickTargets[i]
			if(Math.pow(event.clientX - target.x, 2) + Math.pow(event.clientY - target.y, 2) < Math.pow(target.r, 2)) {
				currentStop = target.stop
				lastUpdate = currentTime - (refreshTime * 2)
				drawSchedule()
				break
			}
		}
	}
	
	function goToCenter(event) {
		centerX = width / 2
		centerY = height / 2
		zoomLevel = initZoom
		drawSchedule()
	}
	
	
	//Convert a second count into a human-readable time
	function secondsToTime(seconds) {
		var hour = parseInt(seconds / 3600)
		var minute = parseInt((seconds - (hour * 3600)) / 60)
		if(minute < 10) minute = "0" + minute
		if(hour > 12) hour = hour - 12
		if(hour == 0) hour = 12
		return hour + ":" + minute
	}
	
	
	function getTimeSuffix(seconds) {
		var qualifier = ""
		var hour = parseInt(seconds / 3600)
		if(hour == 12) qualifier = "PM"
		if(hour > 12) {
			hour = hour - 12
			qualifier = "PM"
		}
		return qualifier
	}
	
	
	function getCurrentTime() {
		var timeDate = new Date()
		
		if(demoMode) {
			if(!currentTime) currentTime = (8 * 60 * 60)
			return currentTime
		}
		else {
			return (timeDate.getHours() * 60 * 60) + (timeDate.getMinutes() * 60) + timeDate.getSeconds()
			//return (8 * 60 * 60)
		}
	}
	
	
	function getCurrentDay() {
		var timeDate = new Date()
		return weekdays[timeDate.getDay()].toLowerCase()
	}
	
	
	function getHorizon() {
		//var xOff = (width / 2.0) + Math.abs(centerX - (width / 2.0))
		//var yOff = (height / 2.0) + Math.abs(centerY - (height / 2.0))
		var xOff = width / 2.0
		var yOff = height / 2.0
		var length = Math.sqrt((xOff * xOff) + (yOff * yOff))
		return radiusToTime(length)
	}
	
	
	function radiusToTime(radius) {
		var secondsToReachRadius = ((radius - middleDotR) / 60.0) * zoomLevel
		return currentTime + secondsToReachRadius
		
		//var secondsToReachRadius = ((radius - middleDotR) / 40000.0) * zoomLevel
		//var time = currentTime + secondsToReachRadius
		//return time * Math.pow(radius, 0.52)
	}
	
	
	function timeToRadius(time) {
		var secondsToReachTime = time - currentTime
		return middleDotR + ((secondsToReachTime / zoomLevel) * 60.0)
		
		//var secondsToReachTime = time - currentTime
		//var radius = middleDotR + ((secondsToReachTime / zoomLevel) * 40000.0)
		//return radius / Math.pow(radius, 0.52)
	}
	
	
	function drawPath(angle, startTime, endTime) {
		var startLength = timeToRadius(startTime)
		var endLength = timeToRadius(endTime)
		
		var xDiff = Math.cos(angle) * startLength
		var yDiff = Math.sin(angle) * startLength
		context.beginPath()
		context.moveTo((centerX) + xDiff, (centerY) - yDiff)
		xDiff = Math.cos(angle) * endLength
		yDiff = Math.sin(angle) * endLength
		context.lineTo((centerX) + xDiff, (centerY) - yDiff)
		context.stroke()
		context.closePath()
	}
	
	
	function drawStopLabel(angle, x, y, r, stopText, stopGap) {
		context.fillStyle = "#ffffff"
		var fontBase = 12 * (stopGap / 120)
		if(fontBase > 20) fontBase = 20
		if(fontBase < 10) fontBase = 10
		context.font = (fontBase * (initZoom / zoomLevel)) + 'px sans-serif'
		context.textBaseline = 'middle';
		context.textAlign = 'left'
		
		context.save()
		context.translate(x, y)
		
		var two = 2 * (initZoom / zoomLevel)
		var three = 3 * (initZoom / zoomLevel)
		var four = 4 * (initZoom / zoomLevel)
		var five = 5 * (initZoom / zoomLevel)
		
		//We have seven possible label arrangements
		if(angle == Math.PI / 4) {
			context.textAlign = 'right'
			context.rotate(0)
			context.fillText(stopText, -r - three, -four)
		}
		else if(angle == 0) {
			context.rotate(-Math.PI / 4)
			context.fillText(stopText, r + four, -four)
		}
		else if(angle == -Math.PI / 4) {
			context.rotate(0)
			context.fillText(stopText, r + five, -four)
		}
		else if(angle == -2 * Math.PI / 4) {
			context.textAlign = 'right'
			context.rotate(-Math.PI / 4)
			context.fillText(stopText, -r - four, -five)
		}
		else if(angle == -3 * Math.PI / 4) {
			context.textAlign = 'right'
			context.rotate(0)
			context.fillText(stopText, -r - five, -two)
		}
		else if(angle == -4 * Math.PI / 4) {
			context.textAlign = 'right'
			context.rotate(Math.PI / 4)
			context.fillText(stopText, -r - four, -five)
		}
		else if(angle == -5 * Math.PI / 4) {
			context.rotate(0)
			context.fillText(stopText, r + five, -four)
		}
		
		context.restore()
	}
	
	
	//Draw the transportation schedule
	function drawSchedule() {
		if(canvas.clientWidth != width || canvas.clientHeight != height) {
			width = canvas.width = canvas.clientWidth
			height = canvas.height = canvas.clientHeight
			centerX = width / 2
			centerY = height / 2
		}
		horizon = getHorizon()
		middleDotR = originalMiddleDotR * (initZoom / zoomLevel)
		context.clearRect(0, 0, width, height)
		
		//Draw background
		context.beginPath()
		context.fillStyle = "#000000"
		context.fillRect(0, 0, width, height)

		//Get current time
		currentTime = getCurrentTime()
		currentDay = getCurrentDay()
		
		//Draw concentric circles
		var minuteSpacing = 15 * 60
		var circleTime = currentTime + (minuteSpacing - (currentTime % minuteSpacing))
		while(circleTime < horizon) {
			r = timeToRadius(circleTime)
			context.beginPath()
			context.arc(centerX, centerY, r, 0, Math.PI * 2, true)
			context.strokeStyle = "#666666"
			context.lineWidth = 2.0
			context.stroke()

			//Draw the time label
			context.fillStyle = "#ffffff"
			context.font = (16 * (initZoom / zoomLevel)) + 'px sans-serif'
			context.textAlign = 'center'
			context.textBaseline = 'bottom'
			
			if(r >= middleDotR) {
				context.fillText(secondsToTime(circleTime), centerX, (centerY) - r - (1 * (initZoom / zoomLevel)))
			}

			if(circleTime - currentTime > 60 * 60) minuteSpacing += (10 * 60)
			circleTime += minuteSpacing
		}
		
		//Find applicable routes
		if(currentTime - lastUpdate > refreshTime || goodRoutes == null) {
			lastUpdate = currentTime
			bestTimes = Array()
			goodRoutes = Array()
			for(var i = 0; i < data.length; i++) {
				var route = data[i]
			
				//If the route is running today
				if(route[currentDay]) {
					var thisRoute = Array()
					thisRoute.push(new Array())
					thisRoute[0].push(i)
					
					thisRoute[0].push('')
					
					var haveRoute = false
					for(var j = 0; j < route["stops"].length; j++) {
						var stop = route["stops"][j]
					
						//Start at our current location
						if(stop.id == currentStop && stop.time > currentTime && stop.time < getHorizon()) {
							haveRoute = true
						}
						
						//If we are tracking a route, add this stop to it
						if(haveRoute) {
							var thisStop = {'id':stop['id'], 'time':stop['time'], 'name':stop['name']}
							
							//Move the route to the appropriate side if possible
							if(thisStop.id == "San Jose Caltrain") thisRoute[0][1] = 'l'
							else if(thisStop.id == "San Francisco Caltrain") thisRoute[0][1] = 'r'
							
							//If this is the current best time, record it
							if(bestTimes[thisStop.id]) {
								if(thisStop.time < bestTimes[thisStop.id]) {
									bestTimes[thisStop.id] = thisStop.time
								}
							}
							else if(thisStop.time) {
								bestTimes[thisStop.id] = thisStop.time
							}
						
							thisRoute.push(thisStop)
						}
					}
					if(haveRoute) goodRoutes.push(thisRoute)
				}
			}
		}
		
		clickTargets = new Array()
		var currentStopName
		if(goodRoutes.length > 0) currentStopName = goodRoutes[0][1]['name']
		else currentStopName = currentStop
		
		//Sort routes by soonest access time
		goodRoutes.sort(function lemma(a,b) {return a[1]['time'] - b[1]['time']})
		
		//Plot only shortest-route points
		var routesSeen = Array()
		for(var i = 0; i < goodRoutes.length; i++) {
			thisRoute = Array()
			var routeID = goodRoutes[i][0][0]
			thisRoute.push(routeID)
			routesSeen.push(routeID)
			
			var routePref = goodRoutes[i][0][1]
			
			for(var j = 1; j < goodRoutes[i].length; j++) {
				var stop = goodRoutes[i][j]
				if(stop.time <= bestTimes[stop.id] || stop.id == currentStop) {
						thisRoute.push(stop)
				}
			}
			
			//If we have a route with points
			var position = -1
			if(thisRoute.length > 2) {
				var routeID = thisRoute[0]
				
				//Check if we've already placed it
				for(var j = 0; j < occupied.length; j++) {
					if(occupied[j] == routeID) {
						position = j
						break
					}
				}
				
				//Otherwise, see if we have space for it
				if(position == -1) {
					var prefs = neutralPref
					if(routePref == 'r') prefs = rightPref
					else if(routePref == 'l') prefs = leftPref
					
					for(var j = 0; j < prefs.length; j++) {
						occupiedI = prefs[j]

						//Check if we can free up the position
						var existingRouteID = occupied[occupiedI]
						if(existingRouteID != -1 && routesSeen.indexOf(existingRouteID) == -1) {
							occupied[occupiedI] = -1
						}

						//If the position is free, use it
						if(occupied[occupiedI] == -1) {
							position = occupiedI
							occupied[occupiedI] = routeID
							break
						}
					}
				}
			}
			
			//If we can plot this route, do so
			if(position >= 0) {
				//Sort the route by time
				thisRoute.splice(0, 1)
				thisRoute.sort(function lemma(a,b) {return a.time - b.time})
				
				var startTime = thisRoute[0].time
				var endTime = thisRoute[thisRoute.length - 1].time
				var angle = angles[position]
				var color = colors[position]
				
				//Draw the wait line
				context.strokeStyle = color
				context.lineWidth = 4 * (initZoom / zoomLevel)
				drawPath(angle, currentTime, startTime)
				
				//Draw the actual line
				context.lineWidth = 14 * (initZoom / zoomLevel)
				drawPath(angle, startTime, endTime)
				
				var closestStopGap = -1
				var lastStopTime = -1
				//Find the closest stop gap
				for(var j = 1; j < thisRoute.length; j++) {
					var thisStop = thisRoute[j]
					
					if(lastStopTime >= 0) {
						if(closestStopGap == -1 || thisStop['time'] - lastStopTime < closestStopGap) {
							closestStopGap = thisStop['time'] - lastStopTime
						}
					}
					lastStopTime = thisStop['time']
				}
				if(closestStopGap == -1) closestStopGap = 60 * 60
				
				//Draw the stops
				for(var j = 1; j < thisRoute.length; j++) {
					//Draw the dot
					var thisStop = thisRoute[j]
					r = 10.0 * (initZoom / zoomLevel)
					var stopTimeLength = timeToRadius(thisStop.time)
					xDiff = Math.cos(angle) * stopTimeLength
					yDiff = Math.sin(angle) * stopTimeLength
					context.beginPath()
					context.fillStyle = "#000000"
					if(thisStop['id'] == highlightedStop) context.fillStyle = color
					context.strokeStyle = color
					context.lineWidth = 4 * (initZoom / zoomLevel)
					context.arc(centerX + xDiff, centerY - yDiff, r, 0, Math.PI * 2, true)
					context.fill()
					context.stroke()
					
					//Label the stop
					var stopText = thisStop['name']
					stopText = stopText.replace(" Caltrain", "")
					stopText = stopText.replace(" BART", "")
					stopText = stopText.replace(" Metrolink Station", "")
					drawStopLabel(angle, centerX + xDiff, centerY - yDiff, r, stopText, closestStopGap)
					
					//Add the stop to the click targets
					var thisTarget = new Array()
					thisTarget.x = centerX + xDiff
					thisTarget.y = centerY - yDiff
					thisTarget.r = r
					thisTarget.stop = thisStop['id']
					clickTargets.push(thisTarget)
				}
			}
		}
		
		//Draw middle dot
		context.beginPath()
		context.lineWidth = 10 * (initZoom / zoomLevel)
		context.arc(centerX, centerY, middleDotR, 0, Math.PI * 2, true)
		context.fillStyle = "#000000"
		context.strokeStyle = "#ffffff"
		context.fill()
		context.stroke()
		
		//Draw middle text
		context.fillStyle = "#ffffff"
		context.font = 30 * (initZoom / zoomLevel) + 'px sans-serif'
		context.textAlign = 'center';
		context.textBaseline = 'middle';
		context.fillText(currentStopName.substring(0, 2), centerX, centerY)
		
		//Draw time and location
		context.fillStyle = "#ffffff"
		context.font = '30px sans-serif'
		context.textAlign = 'left';
		context.textBaseline = 'top';
		context.fillText(currentStopName, 10, 5)
		var dayText = currentDay.charAt(0).toUpperCase() + currentDay.slice(1)
		context.fillText(dayText + ', ' + secondsToTime(currentTime) + ' ' + getTimeSuffix(currentTime), 10, 40)
		
		if(demoMode && currentTime) currentTime += refreshTime * 10
		setTimeout("drawSchedule()", refreshTime * 1000)
	}
</script>

</html>
