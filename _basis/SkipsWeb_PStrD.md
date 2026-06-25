Prosjekt Kunnskaps Dokument (PKD)

Prosjektets navn er SKIPSWEB

RESSURSRAMMEBETINGELSER:
DB som skal gis et GUI i prosjektet ligger på et Webhotell, OnNet.no.
Webhotellet har MySQL ver 8.3.28 og støtter Cron.
Jeg har en MSc i "Design of Information Systems" fra 1980. Jeg har utviklet applikajoner i og kan lese VBA kode. Jeg har god erfaring med design av Relasjonsdatabaser.
Jeg har tilgjengelig VB Studio Code, Notebook++, Access, phpMySQL ver 5.2.2, MySQL Workbench ver 8.0 CE og xampp.

DB har et Schema som angitt i "SkipsWeb_SCHEMA v*.sql" fil.

Data for connection string - deployert versjon: 
vertsnavn (host): hostmaster.onnet.no 
databasenavn: skipsweb_skipsdb
brukernavn (MySQL-konto): skipsweb_webuser (SELECT-rettigheter)
passord: ########## (fås oppgitt hvis/når nødvendig)
port: 3306
charset: utf8mb4 (behøves ikke for connection)

Data for connection string - utvikling/testing: 
vertsnavn (host): localhost 
databasenavn: skipsdb
brukernavn (MySQL-konto): root
passord: ""
port: 3306 ?
charset: utf8mb4 (behøves ikke for connection)

php-filene (så langt) er arrangert i følgende struktur:

skipsweb/
├── .htaccess                			# URL-omskriving, sikkerhetsregler
├── index.php               			# Landingside
├── login.php                			# Innlogging
├── register.php             			# Brukerregistrering
│
├── admin/      			
│   ├── dashboard.php           		# Navigasjonsside
│   ├── fart_delete.php           		# Sletting av fartøy
│   ├── fart_edit.php           		# Endring av fartøysdata
│   ├── fart_nyfart.php           		# Nytt fartøy
│   ├── fart_nyspes.php          		# Ny teknisk spesifikasjon, eksisterende fartøy
│   ├── fart_nytid.php          		# Nytt navn, eierskap, nasjonalitet, registerhavn e.l., eksisterende fartøy
│   ├── param_admin.php          		# Admin av parametyertabeller
│   ├── param_table.php          		# Tabell med parameter tabeller		
│   ├── sw_admin.php           			# Navigasjonsside, fartøysendringer og parametre.
│   ├── users.php           			# Brukere
│   ├── verft.php           			# Verftsdetaljer (under utarbeidelse)
│   └── verftadmin.php        			# Verftsadmin (under utarbeidelse)
│
├── assets/
│   ├── css/  
│   │	└── app.css  					# css-fil
│   │   
│   ├── img/ 
│   │	├── hero/ 						# Rotator bilder        			
│   │   │   ├── hero1.jpg         		# Bilde 1
│   │   │   ├── hero2.jpg              	# Bilde 2
│   │   │   ├── hero3.jpg              	# Bilde 3
│   │   │   ├── hero4.jpg              	# Bilde 4
│   │   │   └── hero5.jpg              	# Bilde 5
│   │   │
│   │	├── skip/ 						# Bilder for fartoysdetaljer.php og liknende fartøysspesifikke filer
│   │   │   ├── placeholder.jpg         # Bilde Placeholder
│   │   │   ├── aaa..jpg              	# Bilde aaa ... (Finnes ikke på det nåværende)
│   │   │   ├── .....jpg              	# Bilde ....... (Finnes ikke på det nåværende)
│   │   │   ├── yyy..jpg              	# Bilde yyy.....(Finnes ikke på det nåværende)
│   │   │   └── zzz..jpg              	# Bilde zzz ....(Finnes ikke på det nåværende)
│   │   │
│   │   ├── fart_nat_1.jpg           	# Bilde av skip
│   │   ├── fartoydetaljer_1.jpg        # Bilde av skip
│   │   ├── fart_spes_1.jpg           	# Bilde av skip
│   │   ├── SkipsWeb-logo.jpg           # Logo
│   │	└── SkipsWeb-logo@2x.jpg  		# Logo x2 størrelser
│   │   
│   ├── sound/  
│   │   ├── ????????????.mp3        	# Lydsnutt
│   │	└── betcha_a_dollar.mp3.  		# Lydsnutt
│   │   
│   ├── video/  
│   │   ├── rustholk_large.mp4        	# Videosnutt
│   │   ├── fraktelv_small.mp4          # Videosnutt
│   │   ├── skipihavn_small.mp4         # Videosnutt
│   │   ├── bolgerstrand_small.mp4      # Videosnutt
│   │	└── stillehav_small.mp4  		# Videosnutt
│   │   
│   └── js/  
│   	└── hero-rotator.js 
│
├── config/
│   ├── config.php           			# Database- og app-innstillinger
│   └── constants.php        			# Lokasjonsinnstillinger
│
├── includes/
│   ├── auth.php           				# rolle-/tilgangshjelpere
│   ├── bootstrap.php        			# Laster config, constants, functions og $mysqli = db();
│   ├── db.php           				# ?
│   ├── export_utils.php             	# Hjelpefilert
│   ├── header.php           			# Felles HTML-head + åpning av <body>
│   ├── field_widths.php           		# Feltbredder
│   ├── functions.php        			# Hjelpefunksjoner (validering, formatering mv.)
│   ├── footer.php           			# Felles footerelementer + lukking av <body>
│   ├── lookups           				# Oppslag
│   ├── menu.php             			# Navigasjon
│   └── param_utils.php           		# Felles admin av parametre
│
└── user/
	├── export_fart_soknavn.php       	# Eksport .csv på fartøysnavn, utlisting for søkeresultat
	├── export_fart_sokspes.php       	# Eksport .csv på spesifikasjonsparametre, utlisting for søkeresultat
	├── export_fart_detalj.php       	# Eksport .csv, utlisting for søkeresultat
	├── export_verft_sok.php   			# Eksport .csv verft, utlisting for søkeresultat
	├── export_rederi_sok.php        	# Eksport .csv rederi, utlisting for søkeresultat
	├── fart_detalj.php       			# Søk, utlisting for søkeresultat
	├── fart_spes.php        			# Spesifikasjoner av fartøy
	├── fart_soknavn.php       			# Søk på fartøysnavn, utlisting for søkeresultat
	├── fart_sokspes.php       			# Søk på spesifikasjonsparametre, utlisting for søkeresultat
	├── fart_detalj.php       			# Søk, utlisting for søkeresultat
	├── print_fart_soknavn.php       	# Print på fartøysnavn, utlisting for søkeresultat
	├── print_fart_sokspes.php       	# Print på spesifikasjonsparametre, utlisting for søkeresultat
	├── print_fart_detalj.php       	# Print, utlisting for søkeresultat
	├── print_verft_sok.php   			# Print verft, utlisting for søkeresultat
	├── print_rederi_sok.php        	# Print rederi, utlisting for søkeresultat
	├── verft_sok.phgp   				# Søk verft, utlisting for søkeresultat
	└── rederi_sok.php        			# Søk rederi, utlisting for søkeresultat


Lay-out:
	Filene tilstreber å tilfredsstille følgende generelle krav til layout: 
	+ Side headinger skal være midtstilt.
	+ Knapper benyttes til å starte opp handlinger (f.eks. søks- og detaljfiler), faner brukes til å vise andre meny-sider (f.eks. egen meny for administrator). 
	+ Knapper skal være like store. Knapper skal ha "knappefarge", f.eks. tilsvarende den blå fargen som er valgt for landingsside. Knapper skal ikke være i sidens fulle bredde, men være små, men likevel brede og høye nok til å takle 20 karakterer i valgt "knappefont" 11px. 
	+ Felt og blokker bør være rimlig komprimerte, unngå bruk av store typer (over 12px).
	Spesielt for landingssiden:
	+ Når knapper benyttes må de ikke være duplikater 

Utførelse av endringer:
Alle endringer skal skje basert på enten fil vedlagt i prosjektets dokumenter, en vedlagt fil, eller siste versjon av den relevante filen som ligger på https://github.com/Kringla/ProdSWeb.
Bruk alltid BASE_URL som referanse til rot.

Jeg er redd for å skifte ut feil kode blokker når du foreslår endringer. Derfor må du alltid sørge for enten for
1) å vise hele blokken den skal erstatte, (best for meg)
3) å lage en nedlastbar fullt oppdatert fil, eller 
2) å angi hvilke tre linjer som står FØR innsettingsstedet og hvilke tre linjer som står ETTER innsettingsstedet.

Det er mitt ansvar å holde filene på GitHub oppdaterte.