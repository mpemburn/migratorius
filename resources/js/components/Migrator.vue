<template>
    <div class="max-w-6xl mx-4 sm:px-6 lg:px-8">
        <div class="row align-items-start mt-4 p-2">
            <section class="col border m-1 p-2">
                <h4>From</h4>
                <label for="database_from">Database:</label>
                <select id="database_from" name="from" @change="selectDatabase($event)">
                    <option value="">Select</option>
                    <option v-for="database in databases" :value="database.name">
                        {{ database.label}}
                    </option>
                </select>
                <input type="search" @keyup="filterSubsites" @search="filterSubsites" placeholder="Filter selection"/>
                <button id="migrate_btn" class="btn btn-primary btn-sm" disabled>Migrate</button>
                <img id="loading" class="d-none"
                     src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" alt="" width="24"
                     height="24">
                <div id="error_msg" class="text-danger"></div>
                <div class="mt-2">
                    <select class="border" @change="subsitesSelected" name="subsites_from" id="subsites_from" size="20" multiple>
                        <option v-for="subsites in subsitesFrom" :value="subsites.blogId">
                            [{{ subsites.blogId }}] {{ subsites.siteurl}}
                        </option>
                    </select>
                </div>
            </section>
            <section class="col border m-1 p-2">
                <h4>To</h4>
                <label for="database_to">Database:</label>
                <select id="database_to" name="to" class="mb-1" @change="selectDatabase($event)">
                    <option value="">Select</option>
                    <option v-for="database in databases" :value="database.name">
                        {{ database.label}}
                    </option>
                </select>
                <div class="mt-2">
                    <select class="border" name="subsites_to" id="subsites_to" size="20" multiple disabled>
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
export default {
    props: [
        'databases'
    ],
    data() {
        return {
            fromData: [],
            toData: [],
            subsitesFrom: [],
            currentUrl: '',
            selected: []
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

            axios.get("/subsites?database=" + dbName)
                .then(response => {
                    let data = response.data;
                    if (data.subsites) {
                        if (direction === 'from') {
                            self.fromData = data.subsites;
                            self.currentUrl = data.currentUrl;
                        } else {
                            self.toData = data.subsites;
                        }
                        self.fillSelects();
                        // self.filter.keyup();
                    }
                });
        },
        fillSelects() {
            let disableButton = true;

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
                disableButton = false;
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
        }
    },
    mounted() {
        console.log(this)
    }
}
</script>
