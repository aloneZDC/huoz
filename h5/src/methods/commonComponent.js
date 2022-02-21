import NoData from '@/components/NoData.vue' // ComponentA
import Loading from '@/components/Loading.vue' // ComponentA
import TopHeader from '@/components/TopHeader.vue' // ComponentA
import KeyBord from '@/components/KeyBord.vue'
export default (Vue)=>{
  Vue.component("NoData", NoData);
  Vue.component("Loading", Loading);
  Vue.component("TopHeader", TopHeader);
  Vue.component("KeyBord", KeyBord);
}