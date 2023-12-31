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
                <img ref="loading" class="loading" v-show="isFromLoading"
                     src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" alt="" width="24"
                     height="24">
                <div ref="fromMessage" v-show="showFromMessage" class="text-danger"></div>
                <div class="mt-2">
                    <select class="border" ref="fromList" id="subsites_from" @change="subsitesSelected" size="20" multiple :disabled=fromSubsitesDisabled>
                        <option v-if="subsitesFrom.length > 0" v-for="subsites in subsitesFrom" :value="subsites.blogId">
                            [{{ subsites.blogId }}] {{ subsites.siteurl}}
                        </option>
                        <option v-else v-show="fromRetrieved">
                            No subsites found
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
                <span v-if="readyToUndo">
                <button @click="removeSubsites" class="btn btn-primary btn-sm">Submit</button>
                <button @click="cancelUndo" class="btn btn-primary btn-sm">Cancel</button>
                </span>
                <button @click="retrieveUndoables" v-else class="btn btn-primary btn-sm">Undo</button>
                <img ref="loading" class="loading" v-show="isToLoading"
                     src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" alt="" width="24"
                     height="24">
                <div ref="toMessage" v-show="showToMessage" class="text-danger"></div>
                <div class="mt-2">
                    <select class="border" ref="toList" id="subsites_to" @change="toRemoveelected" size="20" multiple :disabled=toSubsitesDisabled>
                        <option v-if="subsitesTo.length > 0" v-for="subsites in subsitesTo" :value="subsites.blogId">
                            [{{ subsites.blogId }}] {{ subsites.siteurl}}
                        </option>
                        <option v-else v-show="toRetrieved">
                            No subsites found
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
            subsitesTo: [],
            fromRetrieved: false,
            toRetrieved: false,
            currentUrl: '',
            selected: [],
            toRemove: [],
            fromSubsitesDisabled: false,
            disableButton: true,
            toSubsitesDisabled: true,
            isFromLoading: false,
            isToLoading: false,
            completed: false,
            showFromMessage: false,
            showToMessage: false,
            readyToUndo: false,
        }
    },
    computed: {
        isButtonDisabled() {
            // evaluate whatever you need to determine disabled here...
            return this.disableButton;
        },
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
            this.isFromLoading = (direction === 'from');
            this.isToLoading = (direction === 'to');
            this.showFromMessage = false;
            axios.get("/subsites?database=" + dbName)
                .then(response => {
                    let data = response.data;
                    let hasData = (data.subsites.length === 0)

                    if (direction === 'from') {
                        self.isFromLoading = false;
                        self.fromRetrieved = hasData;
                        self.fromDatabase = dbName
                        self.fromData = data.subsites;
                        self.currentUrl = data.currentUrl;
                    } else {
                        self.isToLoading  = false;
                        self.toRetrieved = hasData;
                        self.toDatabase = dbName
                        self.toData = data.subsites;
                    }
                    if (self.fromDatabase === self.toDatabase) {
                        self.setMessage('From and To databases cannot be the same.');
                    }

                    self.fillSelects();
                    // self.filter.keyup();
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
            let results = [];
            // Restore all in case there's no match
            this.subsitesFrom = [...this.fromData];
            if (search) {
                this.$refs.fromList.value = null;
                this.subsitesFrom.forEach(option => {
                    let test = option.siteurl.replace(baseUrl, '');
                    if (test.indexOf(search) !== -1) {
                        results.push(option);
                    }
                });
                this.subsitesFrom = results;
            }
        },
        subsitesSelected(event) {
            this.selected = this.gatherSelected(event.target.options);
            this.showFromMessage = (this.selected.length === 0);
        },
        toRemoveelected(event) {
            this.toRemove = this.gatherSelected(event.target.options)
        },
        gatherSelected(options) {
            let container = [];
            for (let option of options) {
                if (option.selected) {
                    container.push(option.value);
                }
            }
            return container;
        },
        getSubsiteById(subsites, id) {
            for (let subsite of subsites) {
                if (subsite.blogId === parseInt(id)) {
                    return subsite.siteurl;
                }
            }

            return '';
        },
        setMessage(text, direction = 'from') {
            if (direction === 'from') {
                this.$refs.fromMessage.innerHTML = text;
                this.showFromMessage = true;
            } else {
                this.$refs.toMessage.innerHTML = text;
                this.showToMessage = true;
            }
        },
        migrate() {
            let query = [
                'databaseFrom=' + this.fromDatabase,
                'databaseTo=' + this.toDatabase,
                '&from='
            ].join('&');

            if (this.completed) {
                this.completed = false;
                this.showFromMessage = false;
                return;
            }

            if (this.selected.length === 0) {
                this.setMessage('You must select at least one subsite.');
                return;
            }

            this.isFromLoading = true;
            // Clear selections and disable fromList
            this.$refs.fromList.value = null;
            this.disableFromList = true;

            // Grab the first item in the selected list
            let subsiteId = this.selected.shift();
            let processing = this.getSubsiteById(this.fromData, subsiteId);
            this.setMessage('Processing: ' + processing + '...');

            axios.post("/do_migration?" + query + subsiteId)
                .then(response => {
                    let data = response.data;
                    console.log(data);
                    if (data.results) {
                        console.log('Ready for the next one...');
                        this.isFromLoading = false;
                        this.disableFromList = false;
                        this.retrieveSubsites(this.toDatabase, 'to');
                        this.completed = (this.selected.length === 0);
                        this.migrate();
                    }
                })
                .catch(response => {
                    console.log(response);
                    this.isFromLoading = false;
                });
        },
        retrieveUndoables() {
            let self = this;
            this.readyToUndo = true;
            this.toSubsitesDisabled = false;
            axios.get("/undoable?database_to=" + this.toDatabase)
                .then(response => {
                    console.log(response.data);
                    self.subsitesTo = response.data.subsites;
                });

        },
        cancelUndo() {
            this.readyToUndo = false;
            this.toSubsitesDisabled = true;
            this.$refs.toList.value = null;
            this.subsitesTo = this.toData;
        },
        removeSubsites() {
            if (this.completed) {
                this.showToMessage = false;
                this.completed = false;
                return;
            }

            let subsiteId = this.toRemove.shift();
            this.isToLoading = true;
            let processing = this.getSubsiteById(this.toData, subsiteId);
            this.setMessage('Removing: ' + processing + '...', 'to');

            axios.post('/remove?database_to=' + this.toDatabase + '&subsite_id=' + subsiteId)
                .then(response => {
                    let data = response.data;
                    console.log(data);
                    if (data.success) {
                        console.log('Ready for the next removal...');
                        this.isToLoading = false;
                        //this.disableToList = false;
                        this.retrieveUndoables();
                        this.completed = (this.toRemove.length === 0);
                        this.removeSubsites();
                    }
                })
                .catch(response => {
                    console.log(response);
                    this.isFromLoading = false;
                });

        }
    },
    mounted() {
        console.log(this.$refs);
    }
}
</script>
