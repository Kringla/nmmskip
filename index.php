<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$page_class = 'page-index';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/menu.php';

// Hent og nullstill flash-melding
$flash_success = (string)($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

$BASE     = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$loggedIn = !empty($_SESSION['user_id']);
?>
<?php if ($flash_success !== ''): ?>
  <div class="container mt-3">
    <div class="alert alert--success"><?= h($flash_success) ?></div>
  </div>
<?php endif; ?>

<?php
$files = glob(__DIR__ . '/assets/img/hero/hero*.{jpg,jpeg,webp,png}', GLOB_BRACE);
natsort($files);

$heroUrls = array_map(fn($p) => $BASE . '/assets/img/hero/' . basename($p), $files);
?>

<!-- HERO med video og tekst oppå -->
<section class="container mt-3 sw-hero">
  <div class="sw-hero-video-wrap">
    <video
      class="sw-hero-video"
      autoplay
      loop
      muted
      playsinline
      preload="metadata"
      aria-label="Rustholk - maritimt videoklipp"
    >
      <source src="<?= url('/assets/video/skipsweb_2.mp4') ?>" type="video/mp4">
      Din nettleser støtter ikke video-taggen.
    </video>

    <div class="sw-hero-overlay sw-hero-overlay--intro"
         data-show-delay="0"
         data-hide-delay="3000"
         data-hide-duration="2000">
      <h1 class="stor-overskrift">Søk etter fartøyer på flere måter</h1>
      <p class="medium-overskrift">Les om hvordan under! Men: Nyt først noen sekunder ...</p>
    </div>

    <!-- Tekst oppå video -->
    <div class="sw-hero-overlay" data-show-delay="9000">
      <h1 class="stor-overskrift">Gamle - og litt nyere - fartøyers historie</h1>
      <p class="medium-overskrift">Velkommen til</p>
      <p>
        <img
          class="sw-hero-logo"
          src="<?= h(($BASE !== '' ? $BASE : '') . '/assets/img/skipsweb-logo@4x1.png') ?>"
          alt="SkipsWeb">
      </p>
    </div>
  </div>
</section>

<script>
// Skaler logo til 35% av videohoyden og styr overlay-visningen
(function(){
  function sizeLogo(){
    var video = document.querySelector('.sw-hero-video');
    var logo  = document.querySelector('.sw-hero-logo');
    if (!video || !logo) return;
    var h = video.clientHeight || 0;
    if (h > 0) {
      var target = Math.max(40, Math.round(h * 0.35));
      logo.style.height = target + 'px';
      logo.style.width = 'auto';
    }
  }

  function fadeOverlayOut(overlay, duration){
    if (!overlay || overlay.dataset.fading === '1') return;
    if (!isFinite(duration) || duration < 0) duration = 1000;
    overlay.dataset.fading = '1';
    overlay.classList.add('is-fading-out');
    setTimeout(function(){
      overlay.classList.remove('is-visible');
      overlay.classList.remove('is-fading-out');
      overlay.dataset.fading = '';
    }, duration);
  }

  function scheduleOverlayVisibility(){
    var overlays = document.querySelectorAll('.sw-hero-overlay');
    overlays.forEach(function(overlay){
      var showDelay = parseInt(overlay.getAttribute('data-show-delay') || '0', 10);
      if (!isFinite(showDelay) || showDelay < 0) showDelay = 0;

      setTimeout(function(){
        sizeLogo();
        overlay.classList.add('is-visible');
      }, showDelay);

      var hideDelayAttr = overlay.getAttribute('data-hide-delay');
      if (hideDelayAttr !== null) {
        var hideDelay = parseInt(hideDelayAttr, 10);
        if (isFinite(hideDelay) && hideDelay >= 0) {
          var fadeDuration = parseInt(overlay.getAttribute('data-hide-duration') || '1000', 10);
          if (!isFinite(fadeDuration) || fadeDuration < 0) fadeDuration = 1000;
          setTimeout(function(){
            fadeOverlayOut(overlay, fadeDuration);
          }, showDelay + hideDelay);
        }
      }
    });
  }

  function init(){
    sizeLogo();
    window.addEventListener('resize', sizeLogo);

    var v = document.querySelector('.sw-hero-video');
    if (v) {
      var resizeHandler = function(){ sizeLogo(); };
      v.addEventListener('loadedmetadata', resizeHandler);
      v.addEventListener('loadeddata', resizeHandler);
    }

    scheduleOverlayVisibility();
  }

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init, false);
  }
})();
</script>


<!-- Lydavspilling: automatisk start, fade ut og mute-knapp -->
<div class="sw-audio" aria-live="polite">
  <audio id="swAudio"
         preload="auto"
         src="<?= h(url('/assets/sound/betcha_a_dollar.mp3')) ?>"
         aria-hidden="true"
         tabindex="-1"></audio>
  <button type="button" id="swAudioMute" class="mdc-button mdc-button--raised btn sw-audio-btn" aria-pressed="false" aria-label="Skru av/på lyd"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Skru av lyd</span></button>
  <span id="swAudioNotice" class="sr-only" aria-live="polite"></span>
  <!-- Merk: Autoplay av lyd kan blokkeres av nettleser. Vi forsøker å starte og faller tilbake til første brukerklikk. -->
  <?php if ($loggedIn): ?>
  <a href="<?= h($BASE) ?>/logout.php" class="mdc-button mdc-button--raised btn sw-audio-btn" style="margin-left:auto;"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Logg ut</span></a>
  <?php else: ?>
  <a href="<?= h($BASE) ?>/login.php" class="mdc-button mdc-button--raised btn sw-audio-btn" style="margin-left:auto;"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Logg inn</span></a>
  <?php endif; ?>
</div>

<section class="container mt-3">
  <div class="mdc-card card" style="padding:0 1.5rem;line-height:1.55;">
      <p class="muted">“SkipsWeb” er en database som gir adgang til data for norske og utenlandske fartøyer. De er i hovedsak samlet av <em style="color: blue;">Ole Hajem Fiske</em> gjennom mange års arbeide for <a href="https://marmuseum.no/"  target="_blank">Norsk Maritimt Museum (NMM)</a>. I tillegg inneholder databasen data for de fartøyene som er omtalt i <em>Dampskipspostens</em> 125 numre. 
      Ved oppslag på enkeltfartøyer vil <b>noen</b> av oppslagene vise bilder av fartøyene, slik de vises i <a href="https://digitaltmuseum.no/search?type=Photograph&q=skip/"  target="_blank">Digitalt Museums fotografisamling (<b>DiMu</b>)</a> av skip. Noen er gitt av andre givere. <em>Vær oppmerksom på at bilder funnet der kan være merket med rettighetsangivelser som må følges!</em></p>
      <p style="border:2px solid red; padding:.35rem .75rem; margin:.5rem 0;"><strong>Merk:</strong> Databasen har ingen offisell status ved museet. Riktigheten og oppdatertheten av dataen og lenker er ikke verifisert.</p>
      <p class="muted">Søk i databasen krever ingen innlogging. Kun adgangen til å foreta utskrifter av søkeresultater, administrasjon av databasen og endringer av innhold krever innlogging. Klikk på en av knappene under for å skrive inn en søkestreng med påfølgende søk. Hvis du ønsker adgang til å skrive ut, send en forespørsel om å bli gitt påloggingsrettigheter til <a href="mailto:webmaster@skipsweb.no">webmaster@skipsweb.no</a>. </p>
      <div class="sw-search-cards">
        <div class="sw-search-card">
          <a href="user/fart_soknavn.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">NAVN På FARTØYER</span></a>
          <p>Søk med fritekst på hele eller deler av fartøysnavn, med ev. filter for registerhavn og nasjoner. Det resultere i en liste over fartøyer som svarer til søkekriteriene.</p>
        </div>
        <div class="sw-search-card">
          <a href="user/rederi_sok.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">REDERIERS FARTØYER</span></a>
          <p>Søk med fritekst på hele eller deler av rederiers navn. Det resultere i en liste over de fartøyer det valgte rederiet har disponert.</p>
        </div>
        <div class="sw-search-card">
          <a href="user/verft_sok.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">VERFTS BYGDE FARTØYER</span></a>
          <p>Søk med fritekst å hele eller deler av verfts navn. Det vil resultere i en liste over de fartøyer som verftet har bygd.</p>
        </div>
      </div>
      <p class="muted">Et søk skjelner ikke mellom store og små bokstaver i søkestrengen. Ved søk på fartøysnavn skal kun hele eller deler av navnet brukes, <strong>uten</strong> 'M/S', D/S' o.l. <br> Felles for alle de listete fartøyene er at alle data, historiske hendelser og spesifikasjoner som er lagret i databasen, samt linker til andre kilder kan vises ved å velge fartøy. Fartøyenes <em>CV (historikk)</em> finnes for ca. 60&nbsp;% av fartøyene i databasen.</p>
      </div> 
      
    <div class="mdc-card card" style="padding:0 1.5rem;">
      <p class = "muted">Databasens innhold av data om det enkelte fartøyene i basen varierer. For flere detaljer kan en finne de i databaser som er bedre vedrørende fartøyers detaljer og tekniske spesifikasjoner. For gode, detaljerte beskrivelser av fartøyer vises det til f.eks. 
       <a href="https://kulturnav.org/page/class?class=NavalVessel&baseClass=NamedObject#N" target="_blank" rel="noopener"kulturnav.org</a>, nordiske museers egen søkbare database, <a href="https://www.sjohistorie.no/no" target="_blank" rel="noopener">sjøhistorie.no</a>, en svært godt utviklet (og mye større) database. Ellers kan du prøve Norsk Skipsfarthistorisk Selskaps
        skipsdatabase på <a href="https://skipshistorie.net/" target="_blank" rel="noopener">skipshistorie.net</a>, eller Krigsseilerregisterets fartøyer på <a href=https://krigsseilerregisteret.no/skip?q target="_blank" rel="noopener">krigsseilerregisteret.no</a>.
      </p>
      <p class="muted">Søkene forbedres fortløpende basert på mottatte kommentarer. Innholdet utvikles fortløpende.</p>
	    <p class="muted">Lykke til med å finne det fartøyet du er på jakt etter!</p>
    </div>
  </div>
</section>


<script>
/* Fallback-rotator: kjører KUN hvis hero-rotator.js ikke har initialisert */
(function(){
  function parseImages(raw){
    // Prøv JSON først (som i din oppdaterte hero-rotator.js)
    try { return JSON.parse(raw); } catch(e){}
    // Deretter en tolerant parser for array-litteraler
    raw = (raw||'').trim().replace(/^\[/,'').replace(/\]$/,'');
    return raw ? raw.split(',').map(function(s){ return s.trim().replace(/^['"]|['"]$/g,''); }).filter(Boolean) : [];
  }

  function initFallback(){
    var el = document.querySelector('.hero.hero-rotator');
    if(!el) return;
    var imgs = parseImages(el.getAttribute('data-images') || '[]');
    if(imgs.length === 0) return;

    // Sett startverdier (dersom ikke allerede satt via inline style)
    if (!getComputedStyle(el).getPropertyValue('--hero-a')) {
      el.style.setProperty('--hero-a', 'url("'+imgs[0]+'")');
    }
    el.style.setProperty('--hero-b', 'url("'+(imgs[1]||imgs[0])+'")');

    if(imgs.length < 2) return;

    var i = 1, sideB = true, interval = parseInt(el.getAttribute('data-interval')||'6000',10);
    if (!isFinite(interval) || interval < 1000) interval = 6000;

    setInterval(function(){
      i = (i+1) % imgs.length;
      var url = 'url("'+imgs[i]+'")';
      el.style.setProperty(sideB ? '--hero-a' : '--hero-b', url);
      sideB = !sideB;
      el.classList.toggle('is-b', !sideB);
    }, interval);
  }

  document.addEventListener('DOMContentLoaded', function(){
    // Hvis global HeroRotator finnes (fra hero-rotator.js), la den styre - ellers fallback.
    if (window.HeroRotator && typeof window.HeroRotator.init === 'function') {
      window.HeroRotator.init();
    } else {
      initFallback();
    }
  });
})();
</script>

<script>
// Lydlogikk for index: autoplay, fade etter 5s (til 0 i løpet av 30s), mute-knapp
(function(){
  function onReady(fn){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, {once:true});
    else fn();
  }

  onReady(function(){
    var audio = document.getElementById('swAudio');
    var btn   = document.getElementById('swAudioMute');
    var notice= document.getElementById('swAudioNotice');
    if (!audio || !btn) return;

    // For maksimal sjanse for autoplay i Chrome: start MUTED, så forsøk å unmute etter at play() lykkes
    var userToggledMute = false;
    audio.muted = true;
    audio.volume = 1.0;

    function updateButton(){
      var muted = !!audio.muted || audio.volume === 0;
      btn.setAttribute('aria-pressed', String(muted));
      btn.textContent = muted ? 'Skru på lyd' : 'Skru av lyd';
    }

    function tryAutoplay(){
      var p = audio.play();
      if (p && typeof p.then === 'function') {
        p.then(function(){
          // Playback startet (muted). Forsøk å unmute dersom bruker ikke har mutet selv
          if (audio.muted && !userToggledMute) {
            setTimeout(function(){ audio.muted = false; updateButton(); }, 200);
          }
        }).catch(function(){
          // Autoplay blokkert: vent på første brukerinteraksjon
          if (notice) notice.textContent = 'Trykk for å aktivere lyd';
          var enable = function(){
            audio.play().finally(function(){ document.removeEventListener('click', enable); if(notice) notice.textContent=''; });
          };
          document.addEventListener('click', enable, {once:true});
        });
      }
    }

    // Fade: start 3s etter første gang lyden faktisk begynner å spille
    function scheduleFadeOnce(){
      var started = false;
      var onPlay = function(){
        if (started) return; started = true;
        setTimeout(function(){
          var durationMs = 150000; // 15s til volum 0
          var stepMs = 100;
          var steps = Math.max(1, Math.floor(durationMs / stepMs));
          var startVol = audio.muted ? 0 : audio.volume;
          var i = 0;
          var iv = setInterval(function(){
            if (audio.paused) { clearInterval(iv); return; }
            i++;
            var ratio = Math.max(0, 1 - (i/steps));
            audio.volume = Math.max(0, +(startVol * ratio).toFixed(3));
            if (i >= steps || audio.volume <= 0.001){ audio.volume = 0; clearInterval(iv); updateButton(); }
          }, stepMs);
        }, 3000);
      };
      audio.addEventListener('play', onPlay, {once:true});
    }

    // Mute-knapp
    btn.addEventListener('click', function(){
      userToggledMute = true;
      audio.muted = !audio.muted;
      // Hvis vi muter når volum er 0, la det være; hvis vi unmuter og volum=0 pga fade, ikke endre volum automatisk
      updateButton();
      if (!audio.paused && !audio.muted) {
        // Sørg for at lyden spiller hvis brukeren skrur på
        tryAutoplay();
      }
    });

    // Stopp lyden når siden forlates
    window.addEventListener('beforeunload', function(){
      try { audio.pause(); } catch(e){}
      try { audio.currentTime = 0; } catch(e){}
      try { audio.src = ''; } catch(e){}
    });

    updateButton();
    scheduleFadeOnce();
    tryAutoplay();
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
