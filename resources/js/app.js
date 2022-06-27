require("./bootstrap");
import { createApp } from "vue";
import LocationSearch from "./components/LocationSearch.vue";

const app = createApp({});
app.component("info", LocationSearch);
app.mount("#store-info");
