<template v-if="restaurantDetails">
    <div class="p-4 md:w-1/3" v-for="restaurantDetail in restaurantDetails">
        <div
            class="h-full border-2 border-gray-200 border-opacity-60 rounded-lg overflow-hidden"
        >
            <img
                class="lg:h-48 md:h-36 w-full object-cover object-center"
                :src="restaurantDetail.photo.pc.l"
            />
            <div class="p-6">
                <h2
                    class="tracking-widest text-xs title-font font-medium text-gray-400 mb-1"
                >
                {{ restaurantDetail.name }}
                </h2>
                <h1 class="title-font text-lg font-medium text-gray-900 mb-3">
                    {{ restaurantDetail.catch }}
                </h1>
                <p class="leading-relaxed mb-3">
                    {{ restaurantDetail.address }}
                </p>
                <div class="flex items-center flex-wrap">
                    <a
                        class="text-indigo-500 inline-flex items-center md:mb-2 lg:mb-0" :href="'detail/'+restaurantDetail.id"
                        > 店舗詳細へ

                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import axios from "axios";
export default {
    data() {
        return {
            homeDirName: location.href,
            restaurantDetails: null,
        };
    },
    methods: {
        // 現在地を配列で返す
        getCurrentLocation: function () {
            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition((position) => {
                    let coords = position.coords;
                    let current = {
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                    };
                    resolve(current);
                });
            });
        },
    },
    async mounted() {
        // geolocationAPIが非同期のためawait
        let currentLocation = await this.getCurrentLocation();
        axios
            .post(this.homeDirName + "nearByRestaurant", {
                params: {
                    latitude: currentLocation.latitude,
                    longitude: currentLocation.longitude,
                },
            })
            .then((response) => {
                this.restaurantDetails = response["data"];
                if(!response["data"]["length"]) alert("お店が見つかりませんでした！");
            });
    },
};
</script>
