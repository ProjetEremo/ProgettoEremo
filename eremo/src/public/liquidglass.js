// File: liquidglass.js
// Inizializza l'effetto "Liquid Glass"
document.addEventListener('DOMContentLoaded', function() {
  // Controlla se la libreria LiquidWeb è stata caricata
  if (typeof LiquidWeb !== 'undefined') {
    // Un piccolo ritardo per assicurare che tutti gli elementi della pagina siano stati renderizzati
    setTimeout(() => {
      // Seleziona TUTTI gli elementi che hanno l'attributo 'data-liquid'
      const liquidElements = document.querySelectorAll('[data-liquid]');

      if (liquidElements.length > 0) {
        // Definiamo configurazioni diverse per tipi di elementi differenti
        const liquidConfigs = {
          // Configurazione di default (usata per il banner dell'eroe)
          default: {
            scale: 15,
            blur: 3,
            saturation: 130,
            aberration: 35,
            mode: 'prominent'
          },
          // Configurazione specifica per il bottone flottante (FAB), più delicata
          fab: {
            scale: 15,
            blur: 3,
            saturation: 130,
            aberration: 35,
            mode: 'prominent'
          }
        };

        // Applica l'effetto a ogni elemento trovato
        liquidElements.forEach(element => {
          // Determina quale configurazione usare in base all'ID dell'elemento.
          // Se è il bottone dell'AI, usa la config 'fab', altrimenti quella di default.
          const configKey = (element.id === 'ai-assistant-fab') ? 'fab' : 'default';
          const config = liquidConfigs[configKey];

          // Inizializza l'effetto sull'elemento con la configurazione scelta
          new LiquidWeb(element, config);
        });

        console.log(`Effetto Liquid Glass inizializzato su ${liquidElements.length} elemento/i.`);
      }
    }, 100); // 100ms di ritardo
  } else {
    console.warn('Libreria LiquidWeb non caricata. Effetto non applicato.');
  }
});