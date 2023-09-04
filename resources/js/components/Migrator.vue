<template>
    <div class="max-w-6xl mx-4 sm:px-6 lg:px-8">
        <div class="row align-items-start mt-4 p-2">
            <section class="col border m-1 p-2">
                <h4>From</h4>
                <label for="database_from">Database:</label>
                <select ref="database_from" name="from" @change="selectDatabase($event)">
                    <option value="">Select</option>
                    <option v-for="database in databases" :value="database.name">
                        {{ database.label}}
                    </option>
                </select>
                <input type="search" @keyup="filterSubsites" @search="filterSubsites" placeholder="Filter selection"/>
                <button @click="migrate" class="btn btn-primary btn-sm" :disabled=isButtonDisabled>Migrate</button>
                <img ref="loading" v-show="isLoading"
                     src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" alt="" width="24"
                     height="24">
                <div ref="message" v-show="showMessage" class="text-danger">Yo</div>
                <div class="mt-2">
                    <select class="border" ref="fromList" id="subsites_from" @change="subsitesSelected" size="20" multiple :disabled=isFromListDisabled>
                        <option v-for="subsites in subsitesFrom" :value="subsites.blogId">
                            [{{ subsites.blogId }}] {{ subsites.siteurl}}
                        </option>
                    </select>
                </div>
            </section>
            <section class="col border m-1 p-2">
                <h4>To</h4>
                <label for="database_to">Database:</label>
                <select ref="database_to" name="to" class="mb-1" @change="selectDatabase($event)">
                    <option value="">Select</option>
                    <option v-for="database in databases" :value="database.name">
                        {{ database.label}}
                    </option>
                </select>
                <div class="mt-2">
                    <select class="border" ref="toList" id="subsites_to" size="20" multiple disabled>
                        <option v-for="subsites in subsitesTo" :value="subsites.blogId">
                            [{{ subsites.blogId }}] {{ subsites.siteurl }}
                        </option>
                    </select>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
export default {
    props: [
        'databases'
    ],
    data() {
        return {
            fromDatabase: '',
            toDatabase: '',
            fromData: [],
            toData: [],
            subsitesFrom: [],
            currentUrl: '',
            selected: [],
            disableFromList: false,
            disableButton: true,
            isLoading: false,
            showMessage: false
        }
    },
    computed: {
        isButtonDisabled() {
            // evaluate whatever you need to determine disabled here...
            return this.disableButton;
        },
        isFromListDisabled() {
            return this.disableFromList;
        }
    },
    methods: {
        selectDatabase(event) {
            let dbName = event.target.value;
            let direction = event.target.name;
            this.retrieveSubsites(dbName, direction);
        },
        retrieveSubsites(dbName, direction) {
            let self = this;
            if (dbName === '') {
                this.clearData(direction);

                return;
            }
            axios.get("/subsites?database=" + dbName)
                .then(response => {
                    let data = response.data;
                    if (data.subsites) {
                        if (direction === 'from') {
                            self.fromDatabase = dbName
                            self.fromData = data.subsites;
                            self.currentUrl = data.currentUrl;
                        } else {
                            self.toDatabase = dbName
                            self.toData = data.subsites;
                        }
                        self.fillSelects();
                        // self.filter.keyup();
                    }
                });
        },
        clearData(direction) {
            if (direction === 'from') {
                this.fromData = [];
                this.subsitesFrom.splice(0, this.subsitesFrom.length);
                this.currentUrl = '';
            } else {
                this.toData = [];
                this.subsitesTo.splice(0, this.subsitesTo.length);
            }
            this.fillSelects();
        },
        fillSelects() {
            this.disableButton = true;

            if (this.toData.length > 0 && this.fromData.length > 0) {
                this.toData.forEach(item => {
                    let siteUrl = new URL(item.siteurl).pathname;
                    // Find any subsite that ends with the same pathname
                    let index = this.fromData.findIndex((obj) => {
                        return obj.siteurl.endsWith(siteUrl);
                    });
                    // Remove item from array
                    if (index !== -1) {
                        this.fromData.splice(index, 1);
                    }
                });
                this.disableButton = false;
            }
            this.subsitesFrom = this.fromData;
            this.subsitesTo = this.toData;
        },
        filterSubsites(event) {
            let search = event.target.value;
            let baseUrl = 'https://' + this.currentUrl;
            // Restore all in case there's no match
            this.subsitesFrom = [...this.fromData];
            if (search) {
                this.subsitesFrom.forEach(option => {
                    let index = this.subsitesFrom.findIndex((from) => {
                        let test = from.siteurl.replace(baseUrl, '');
                        return (test.indexOf(search) === -1);
                    });
                    // Remove item from array
                    if (index !== -1) {
                        this.subsitesFrom.splice(index, 1);
                    }
                });
            }
        },
        subsitesSelected(event) {
            this.selected = [];
            for (let option of event.target.options) {
                if (option.selected) {
                    this.selected.push(option.value);
                }
            }
        },
        getSubsiteById(subsites, id) {
            for (let subsite of subsites) {
                if (subsite.blogId === parseInt(id)) {
                    return subsite.siteurl;
                }
            }

            return '';
        },
        setMessage(text) {
            this.$refs.message.innerHTML = text;
            this.showMessage = true;
        },
        migrate() {
            let query = [
                'databaseFrom=' + this.fromDatabase,
                'databaseTo=' + this.toDatabase,
                '&from='
            ].join('&');

            if (this.selected.length === 0) {
                this.showMessage = false;
                return;
            }
            this.isLoading = true;
            // Clear selections and disable fromList
            this.$refs.fromList.value = null;
            this.disableFromList = true;

            // Grab the first item in the selected list
            let subsiteId = this.selected.shift();
            let processing = this.getSubsiteById(this.fromData, subsiteId);
            this.setMessage('Processing: ' + processing);

            axios.post("/do_migration?" + query + subsiteId)
                .then(response => {
                    let data = response.data;
                    console.log(data);
                    if (data.results) {
                        console.log('Ready for the next one...');
                        this.isLoading = false;
                        this.disableFromList = false;
                        this.retrieveSubsites(this.toDatabase, 'to');
                        this.migrate();
                    }
                })
                .catch(response => {
                    console.log(response);
                    this.isLoading = false;
                });

        }
    },
    mounted() {
        console.log(this.$refs);
    }
}
</script>
