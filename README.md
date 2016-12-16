# socialbet-api

Task:<br/></br/>

Parse API data from the bookmaker Marathonbet.<br/></br/>

Look at the data in the data/ directory. This is the output they provide us.<br/>
We need to create a class that:<br/>
	* Extends the class "Bookmakers" in this repository (and implements the abstract functions defined there)<br/>
	* Parses these XML files and convert all the data to a proper format listed in the "Bookmakers" class<br/>
	* Try using SimpleXMLElement() for parsing the actual XML because it's fast<br/><br/><br/>

Documentation:<br/>
	* There isn't much, but feel free to search google and to ask me.<br/>
	* You might need to read up on the internet to understand the different kinds of bets, otherwise you probably won't be able to implement the correct formatting<br/>

<strong>Bookmaker checklist:</strong>

* Does every sport, league, event, market or participant return at least 1 "meta" value?
	- E.g. league_id, event_id, market_id, participant_id

* `sports`
	- Are we returning data for all possible sports?

* `markets`
	- do we have market type for all? 
	- does the market name incorporate the market type?
	- if we have a separate data from the bookmaker, that designates half-time/full-time/round 1/round 2/etc, is that data incorporated in them market name?
	- are we returning the name?
	- is the market_type described in the market_name (the way WilliamHill does)? Use the readable_market_type( $market_type ) method from class-bookmakers.php to add the market_type in the end of the market name if it's not incorporated by the bookmaker already.

* `events`
	- Do we return the name?
	- Do we return the start date?
	- Is the start date in GMT time?
	- are we returning the name?

* `participants`
	- Do we return the name?
	- Are we returning handicap?
	- Are we returning price data?

