import "../sass/app.scss";
import { bootstrapWhisDefaults } from "./helpers/framework-defaults";

export {
  WHIS_DEFAULT_OPTIONS,
  defineWhisDefaults,
  getWhisDefaults,
  resetWhisDefaults,
  createWhisConfig,
  initWhisDefaults,
  bootstrapWhisDefaults,
  getWhisInstance,
  refreshWhisDefaults,
  destroyWhisDefaults,
} from "./helpers/framework-defaults";

bootstrapWhisDefaults();

/**
 * Inicialización default del framework.
 *
 * El usuario puede usar esto tal cual, o borrar esta línea
 * e inicializar manualmente con initWhisDefaults({...}).
 
    bootstrapWhisDefaults();
    import "../sass/app.scss";
    import { initWhisDefaults } from "./framework-defaults";

    initWhisDefaults({
    modules: {
        stats: false,
        splideMediaControl: false,
    },

    ajaxForms: {
        csrf: true,
        sendAs: "urlencoded",
    },

    splide: {
        options: {
        type: "loop",
        autoplay: true,
        interval: 5000,
        },

        pageOptions: {
        eventos: {
            autoplay: false,
            arrows: true,
            pagination: true,
        },
        },
    },

    images: {
        rootMargin: "200px",
        maxRetries: 5,
    },

    videos: {
        autoplay: false,
        pauseOnExit: true,
    },
    });
**/